<?php

namespace App\Services;

use App\Enums\ShopPermission;
use App\Enums\ShopUserType;
use App\Models\Shop;
use App\Models\User;
use App\Models\UserShop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Xodim (seller) yaratish, tasdiqlash va ruxsatlarni boshqarish.
 *
 * Xodim qo'shish 100% bepul va cheklovsiz. Kirishni cheklash faqat
 * admin bloklash (users.blocked_at) orqali amalga oshiriladi.
 */
class EmployeeService
{
    private const PENDING_TTL = 600; // 10 daqiqa

    public function __construct(
        private readonly OtpService $otp,
    ) {}

    // ─── Yaratish oqimi ─────────────────────────────────────────────────────

    /**
     * Xodim qo'shishni boshlaydi: tekshiruv + xodim telefoniga OTP yuboradi.
     * Ma'lumotlar tasdiqlashgacha keshda saqlanadi.
     */
    public function startInvite(User $owner, Shop $shop, string $name, string $phone, string $password): void
    {
        if (User::where('phone', $phone)->exists()) {
            throw new RuntimeException('phone_taken');
        }

        $record = $this->otp->generate($phone);
        $this->sendOtp($phone, $record->code);
        $this->notifyInvite($owner, $shop, $name, $phone, $record->code);

        Cache::put($this->pendingKey($shop, $phone), [
            'name' => $name,
            'password' => $password,
            'owner_id' => $owner->id,
        ], self::PENDING_TTL);
    }

    /**
     * Kodni tasdiqlaydi va xodimni yaratib, biznesga biriktiradi.
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

        $pivot = DB::transaction(function () use ($shop, $phone, $pending, $owner) {
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

        // Xodim boshqa biznesga biriktirilmagan bo'lsa — akkaunt o'chmaydi,
        // shunchaki kirishi yo'qoladi. (Owner xohlasa keyin qayta taklif qiladi.)
    }

    public function employeePivot(Shop $shop, string $employeeUserId): UserShop
    {
        return UserShop::query()
            ->where('shop_id', $shop->id)
            ->where('user_id', $employeeUserId)
            ->where('user_type', ShopUserType::Seller->value)
            ->with('user')
            ->firstOrFail();
    }

    /** @return \Illuminate\Support\Collection<int,UserShop> */
    public function listForShop(Shop $shop)
    {
        return UserShop::query()
            ->where('shop_id', $shop->id)
            ->where('user_type', ShopUserType::Seller->value)
            ->with('user')
            ->latest()
            ->get();
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

    /**
     * Admin Telegram guruhiga xabar — kim kimni xodim qilmoqchi va qaysi kod
     * yuborilgani. Notifier ichida barcha xatolik yutiladi, ya'ni Telegram
     * ishlamasa ham xodim qo'shish oqimi buzilmaydi.
     */
    private function notifyInvite(
        User $owner,
        Shop $shop,
        string $name,
        string $phone,
        string $code,
    ): void {
        app(RegistrationNotifier::class)
            ->notifyEmployeeInvite($owner, $shop, $name, $phone, $code);
    }
}
