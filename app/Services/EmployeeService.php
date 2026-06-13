<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\SellerSubStatus;
use App\Enums\ShopPermission;
use App\Enums\ShopUserType;
use App\Enums\WalletTransactionType;
use App\Models\Order;
use App\Models\SellerSub;
use App\Models\Shop;
use App\Models\User;
use App\Models\UserShop;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Xodim (seller) yaratish, tasdiqlash, ruxsatlar va pulli o'rin (seat) billingi.
 *
 * Bepul o'rinlar — tarif `max_employees` limiti ichida.
 * Limitdan oshganda har bir xodim oylik to'lov bilan ("pulli o'rin") qo'shiladi.
 * Juma kuni narxda chegirma qo'llanadi.
 */
class EmployeeService
{
    private const PENDING_TTL = 600; // 10 daqiqa
    private const SEAT_PERIOD_DAYS = 30;

    public const SETTING_PRICE_USD = 'employee_seat_usd';
    public const SETTING_FRIDAY_DISCOUNT = 'employee_seat_friday_discount';

    public function __construct(
        private readonly OtpService $otp,
        private readonly WalletService $wallet,
        private readonly ExchangeRateService $exchange,
        private readonly PlanLimitService $limits,
        private readonly SettingService $settings,
    ) {}

    // ─── Narx ───────────────────────────────────────────────────────────────

    public function basePriceUsd(): float
    {
        return $this->settings->getFloat(self::SETTING_PRICE_USD, 2.0);
    }

    public function fridayDiscountPercent(): int
    {
        return $this->settings->getInt(self::SETTING_FRIDAY_DISCOUNT, 50);
    }

    /** Berilgan sanadagi narx (juma bo'lsa chegirma qo'llanadi). */
    public function seatPriceUsd(?Carbon $on = null): float
    {
        $on ??= Carbon::now();
        $price = $this->basePriceUsd();

        if ($on->isFriday()) {
            $price *= (1 - $this->fridayDiscountPercent() / 100);
        }

        return round($price, 2);
    }

    public function seatPriceLocal(?Carbon $on = null): float
    {
        return $this->exchange->convertUsdToUzs($this->seatPriceUsd($on));
    }

    public function isFridayDiscountActive(?Carbon $on = null): bool
    {
        return ($on ?? Carbon::now())->isFriday() && $this->fridayDiscountPercent() > 0;
    }

    // ─── Limit / o'rin ────────────────────────────────────────────────────────

    /** @return array<int,string> */
    private function ownedShopIds(User $owner): array
    {
        return $owner->ownedShops()->pluck('shops.id')->all();
    }

    /** Faqat bepul xodimlar soni (pulli o'rinlar hisobga olinmaydi). */
    public function freeEmployeesUsed(User $owner): int
    {
        $shopIds = $this->ownedShopIds($owner);

        return $shopIds === []
            ? 0
            : SellerSub::query()
                ->whereIn('shop_id', $shopIds)
                ->where('is_paid_seat', false)
                ->distinct('user_id')
                ->count('user_id');
    }

    /** Bepul o'rin mavjudmi? (limit null — cheksiz). */
    public function hasFreeSlot(User $owner): bool
    {
        $plan = $this->limits->effectivePlan($owner);
        $limit = $plan?->max_employees;

        if ($limit === null) {
            return true; // cheksiz yoki billing o'chiq
        }

        return $this->freeEmployeesUsed($owner) < $limit;
    }

    // ─── Yaratish oqimi ─────────────────────────────────────────────────────

    /**
     * Xodim qo'shishni boshlaydi: tekshiruvlar + xodim telefoniga OTP yuboradi.
     * Ma'lumotlar tasdiqlashgacha keshda saqlanadi.
     *
     * @return array{is_paid:bool, price_usd:float, price_local:float, friday_discount:bool}
     */
    public function startInvite(User $owner, Shop $shop, string $name, string $phone, string $password): array
    {
        if (User::where('phone', $phone)->exists()) {
            throw new RuntimeException('phone_taken');
        }

        $isPaid = ! $this->hasFreeSlot($owner);
        $priceLocal = $isPaid ? $this->seatPriceLocal() : 0.0;

        if ($isPaid && ! $this->wallet->hasSufficientBalance($owner, $priceLocal)) {
            throw new RuntimeException('insufficient_balance');
        }

        $record = $this->otp->generate($phone);
        $this->sendOtp($phone, $record->code);

        Cache::put($this->pendingKey($shop, $phone), [
            'name' => $name,
            'password' => $password,
            'owner_id' => $owner->id,
            'is_paid' => $isPaid,
        ], self::PENDING_TTL);

        return [
            'is_paid' => $isPaid,
            'price_usd' => $isPaid ? $this->seatPriceUsd() : 0.0,
            'price_local' => $priceLocal,
            'friday_discount' => $isPaid && $this->isFridayDiscountActive(),
        ];
    }

    /**
     * Kodni tasdiqlaydi va xodimni yaratib, biznesga biriktiradi.
     * Pulli o'rin bo'lsa — owner balansidan yechadi (atomar).
     */
    public function confirm(User $owner, Shop $shop, string $phone, string $code): UserShop
    {
        if (! $this->otp->validate($phone, $code)) {
            throw new RuntimeException('invalid_code');
        }

        $pending = Cache::get($this->pendingKey($shop, $phone));

        if (! $pending || $pending['owner_id'] !== $owner->id) {
            throw new RuntimeException('invite_expired');
        }

        if (User::where('phone', $phone)->exists()) {
            throw new RuntimeException('phone_taken');
        }

        $isPaid = (bool) $pending['is_paid'];
        $priceUsd = $this->seatPriceUsd();
        $priceLocal = $this->exchange->convertUsdToUzs($priceUsd);

        if ($isPaid && ! $this->wallet->hasSufficientBalance($owner, $priceLocal)) {
            throw new RuntimeException('insufficient_balance');
        }

        $pivot = DB::transaction(function () use ($owner, $shop, $phone, $pending, $isPaid, $priceUsd, $priceLocal) {
            // Xodimga TRIAL yaratilmasligi uchun avval seller membership beriladi,
            // keyin user yaratiladi. Trial faqat ShopController da do'kon egasiga
            // ensureTrial() orqali beriladi; foydalanuvchi yaratilishida avtomatik
            // trial yaratadigan observer YO'Q, shuning uchun seller hech qachon
            // owner trialini olmaydi (ensureTrial seller'ni o'zi ham chetlatadi).
            $employee = User::create([
                'name' => $pending['name'],
                'phone' => $phone,
                'password' => $pending['password'],
                'is_accepted_policy' => true,
                'phone_verified_at' => now(),
            ]);

            $employee->shops()->attach($shop->id, [
                'user_type' => ShopUserType::Seller->value,
                'permissions' => ShopPermission::defaults(),
                'invited_by' => $owner->id,
            ]);

            // Seller obunasi — owner obunasidan ALOHIDA jadvalda (trialsiz).
            SellerSub::create([
                'user_id' => $employee->id,
                'shop_id' => $shop->id,
                'owner_id' => $owner->id,
                'status' => SellerSubStatus::Active->value,
                'is_paid_seat' => $isPaid,
                'price_usd' => $isPaid ? $priceUsd : null,
                'starts_at' => now(),
                'ends_at' => $isPaid ? now()->addDays(self::SEAT_PERIOD_DAYS) : null,
            ]);

            if ($isPaid) {
                $order = Order::create([
                    'user_id' => $owner->id,
                    'order_number' => $this->generateOrderNumber(),
                    'type' => OrderType::EmployeeSeat->value,
                    'status' => OrderStatus::Paid->value,
                    'amount_usd' => $priceUsd,
                    'amount_local' => $priceLocal,
                    'currency_code' => 'UZS',
                    'exchange_rate' => $this->exchange->usdToUzs(),
                    'payment_method' => 'balance',
                    'paid_at' => now(),
                    'meta' => ['employee_id' => $employee->id, 'shop_id' => $shop->id],
                ]);

                $this->wallet->debit(
                    $owner,
                    $priceLocal,
                    WalletTransactionType::EmployeeSeatCharge,
                    "Xodim o'rni: {$employee->name}",
                    $order,
                );
            }

            return $this->employeePivot($shop, $employee->id);
        });

        Cache::forget($this->pendingKey($shop, $phone));

        return $pivot;
    }

    // ─── Boshqaruv ────────────────────────────────────────────────────────────

    public function updatePermissions(Shop $shop, string $employeeUserId, array $permissions): UserShop
    {
        $pivot = $this->employeePivot($shop, $employeeUserId);

        $valid = array_values(array_intersect($permissions, ShopPermission::values()));
        $pivot->update(['permissions' => $valid]);

        return $pivot->fresh('user');
    }

    public function remove(Shop $shop, string $employeeUserId): void
    {
        $this->employeePivot($shop, $employeeUserId);

        $shop->users()->detach($employeeUserId);
        SellerSub::where('shop_id', $shop->id)
            ->where('user_id', $employeeUserId)
            ->delete();

        // Xodim boshqa biznesga biriktirilmagan bo'lsa — akkaunt o'chmaydi,
        // shunchaki kirishi yo'qoladi. (Owner xohlasa keyin qayta taklif qiladi.)
    }

    /** Pivot + unga tegishli seller_sub (resource uchun) bilan qaytaradi. */
    public function employeePivot(Shop $shop, string $employeeUserId): UserShop
    {
        $pivot = UserShop::query()
            ->where('shop_id', $shop->id)
            ->where('user_id', $employeeUserId)
            ->where('user_type', ShopUserType::Seller->value)
            ->with('user')
            ->firstOrFail();

        $sub = SellerSub::query()
            ->where('shop_id', $shop->id)
            ->where('user_id', $employeeUserId)
            ->first();

        $pivot->setRelation('sellerSub', $sub);

        return $pivot;
    }

    /** @return \Illuminate\Support\Collection<int,UserShop> */
    public function listForShop(Shop $shop)
    {
        $pivots = UserShop::query()
            ->where('shop_id', $shop->id)
            ->where('user_type', ShopUserType::Seller->value)
            ->with('user')
            ->latest()
            ->get();

        $subs = SellerSub::query()
            ->where('shop_id', $shop->id)
            ->get()
            ->keyBy('user_id');

        return $pivots->each(
            fn (UserShop $pivot) => $pivot->setRelation('sellerSub', $subs->get($pivot->user_id)),
        );
    }

    /** Xodim shu do'konda ishlay oladimi (seller_sub aktivmi). */
    public function isSellerActive(string $shopId, string $employeeUserId): bool
    {
        return SellerSub::query()
            ->where('shop_id', $shopId)
            ->where('user_id', $employeeUserId)
            ->where('status', SellerSubStatus::Active->value)
            ->exists();
    }

    // ─── Oylik yangilanish (scheduled) ─────────────────────────────────────────

    /**
     * Muddati kelgan pulli o'rinlarni yangilaydi.
     * Balans yetsa — yechib uzaytiradi; aks holda past_due qiladi.
     *
     * @return array{renewed:int, suspended:int}
     */
    public function renewDueSeats(): array
    {
        $renewed = 0;
        $suspended = 0;
        $price = $this->seatPriceUsd();
        $priceLocal = $this->exchange->convertUsdToUzs($price);
        $rate = $this->exchange->usdToUzs();

        SellerSub::query()
            ->where('is_paid_seat', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->where('status', '!=', SellerSubStatus::Expired->value)
            ->with(['user'])
            ->chunkById(100, function ($seats) use (&$renewed, &$suspended, $price, $priceLocal, $rate) {
                foreach ($seats as $seat) {
                    $owner = $seat->owner ?? $this->ownerOfShop($seat->shop_id);
                    if (! $owner) {
                        continue;
                    }

                    if (! $this->wallet->hasSufficientBalance($owner, $priceLocal)) {
                        $seat->update(['status' => SellerSubStatus::PastDue->value]);
                        $suspended++;
                        continue;
                    }

                    DB::transaction(function () use ($seat, $owner, $price, $priceLocal, $rate) {
                        $order = Order::create([
                            'user_id' => $owner->id,
                            'order_number' => $this->generateOrderNumber(),
                            'type' => OrderType::EmployeeSeat->value,
                            'status' => OrderStatus::Paid->value,
                            'amount_usd' => $price,
                            'amount_local' => $priceLocal,
                            'currency_code' => 'UZS',
                            'exchange_rate' => $rate,
                            'payment_method' => 'balance',
                            'paid_at' => now(),
                            'meta' => ['employee_id' => $seat->user_id, 'shop_id' => $seat->shop_id, 'renewal' => true],
                        ]);

                        $this->wallet->debit(
                            $owner,
                            $priceLocal,
                            WalletTransactionType::EmployeeSeatCharge,
                            "Xodim o'rni (uzaytirish): {$seat->user?->name}",
                            $order,
                        );

                        $seat->update([
                            'status' => SellerSubStatus::Active->value,
                            'price_usd' => $price,
                            'ends_at' => now()->addDays(self::SEAT_PERIOD_DAYS),
                        ]);
                    });

                    $renewed++;
                }
            });

        return ['renewed' => $renewed, 'suspended' => $suspended];
    }

    public function ownerOfShop(string $shopId): ?User
    {
        $pivot = UserShop::query()
            ->where('shop_id', $shopId)
            ->where('user_type', ShopUserType::Owner->value)
            ->first();

        return $pivot?->user;
    }

    // ─── Yordamchilar ──────────────────────────────────────────────────────────

    private function pendingKey(Shop $shop, string $phone): string
    {
        return "emp_pending:{$shop->id}:{$phone}";
    }

    private function sendOtp(string $phone, string $code): void
    {
        app(SmsService::class)->sendOtp($phone, $code);
    }

    private function generateOrderNumber(): string
    {
        return 'EMP-' . now()->format('ymd') . '-' . strtoupper(Str::random(6));
    }
}
