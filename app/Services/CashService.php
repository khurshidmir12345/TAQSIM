<?php

namespace App\Services;

use App\Enums\CashTransactionSource;
use App\Enums\CashTransactionType;
use App\Models\CashTransaction;
use App\Models\Expense;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Kassa daftari — barcha kirim va chiqimlar bir joyda.
 *
 * Ikkita manba birlashtiriladi:
 *  - `expenses`          — qo'lda kiritilgan xarajatlar (eski ilova hamon shu
 *                          endpointga yozadi, shuning uchun joyida qoldirilgan)
 *  - `cash_transactions` — qo'lda kiritilgan kirimlar va mahsulot chiqimi /
 *                          vozvratdan avtomatik ko'chirilgan yozuvlar
 *
 * Asosiy sahifadan farqi: u faqat ishlab chiqarish va vozvrat haqida gapiradi,
 * kassa esa tashqi pul harakatini ham qo'shib, davr foydasini beradi.
 */
class CashService
{
    public const PERIOD_DAY = 'day';

    public const PERIOD_WEEK = 'week';

    public const PERIOD_MONTH = 'month';

    /**
     * Davr chegaralari. Hafta dushanbadan, oy birinchi kundan boshlanadi.
     *
     * @return array{from:string,to:string}
     */
    public function resolvePeriod(string $period, ?string $reference = null): array
    {
        $tz = config('app.business_timezone');
        $day = $reference !== null
            ? CarbonImmutable::parse($reference, $tz)->startOfDay()
            : CarbonImmutable::now($tz)->startOfDay();

        return match ($period) {
            self::PERIOD_WEEK => [
                'from' => $day->startOfWeek()->toDateString(),
                'to' => $day->endOfWeek()->toDateString(),
            ],
            self::PERIOD_MONTH => [
                'from' => $day->startOfMonth()->toDateString(),
                'to' => $day->endOfMonth()->toDateString(),
            ],
            default => [
                'from' => $day->toDateString(),
                'to' => $day->toDateString(),
            ],
        };
    }

    /**
     * Davr xulosasi: kirim, chiqim, sof natija va kategoriya kesimi.
     *
     * @return array<string,mixed>
     */
    public function summary(Shop $shop, string $from, string $to): array
    {
        $income = $this->incomeRows($shop, $from, $to);
        $expense = $this->expenseRows($shop, $from, $to);

        $incomeTotal = round((float) $income->sum('amount'), 2);
        $expenseTotal = round((float) $expense->sum('amount'), 2);

        return [
            'period' => ['from' => $from, 'to' => $to],
            'income' => [
                'total' => $incomeTotal,
                'count' => $income->count(),
                'by_category' => $this->byCategory($income),
            ],
            'expense' => [
                'total' => $expenseTotal,
                'count' => $expense->count(),
                'by_category' => $this->byCategory($expense),
            ],
            'net' => round($incomeTotal - $expenseTotal, 2),
            'is_profit' => $incomeTotal >= $expenseTotal,
        ];
    }

    /**
     * Birlashtirilgan yozuvlar ro'yxati (eng yangisi tepada).
     *
     * @return LengthAwarePaginator<int,object>
     */
    public function entries(Shop $shop, string $from, string $to, int $perPage = 30): LengthAwarePaginator
    {
        $union = $this->expenseQuery($shop, $from, $to)
            ->unionAll($this->cashQuery($shop, $from, $to));

        return DB::query()
            ->fromSub($union, 'entries')
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Qo'lda yozuv qo'shish.
     *
     * Xarajat eski `expenses` jadvaliga yoziladi — foydalanuvchi qo'lidagi
     * eski ilova xarajatlar ro'yxatini o'sha yerdan o'qiydi va yangi yozuvlar
     * undan yo'qolib qolmasligi kerak.
     *
     * @param  array<string,mixed>  $data
     */
    public function create(Shop $shop, CashTransactionType $type, array $data, ?string $userId): object
    {
        if ($type === CashTransactionType::Expense) {
            $expense = $shop->expenses()->create([
                'category' => $data['category'] ?? 'boshqa',
                'description' => $data['description'] ?? null,
                'amount' => $data['amount'],
                'date' => $data['date'],
                'created_by' => $userId,
            ]);

            return $this->presentExpense($expense);
        }

        $transaction = CashTransaction::create([
            'shop_id' => $shop->id,
            'type' => CashTransactionType::Income,
            'source' => CashTransactionSource::Manual,
            'category' => $data['category'] ?? 'boshqa',
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
            'date' => $data['date'],
            'created_by' => $userId,
        ]);

        return $this->presentTransaction($transaction);
    }

    /**
     * Yozuvni id bo'yicha topadi — qaysi jadvalda ekanini o'zi aniqlaydi.
     *
     * @return array{0:string,1:Expense|CashTransaction}|null ['expense'|'cash', model]
     */
    public function find(Shop $shop, string $id): ?array
    {
        $expense = $shop->expenses()->whereKey($id)->first();

        if ($expense !== null) {
            return ['expense', $expense];
        }

        $transaction = $shop->cashTransactions()->whereKey($id)->first();

        return $transaction !== null ? ['cash', $transaction] : null;
    }

    /** @param  array<string,mixed>  $data */
    public function update(Expense|CashTransaction $model, array $data): object
    {
        $model->update(array_filter([
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'] ?? null,
            'date' => $data['date'] ?? null,
        ], static fn ($v) => $v !== null));

        return $model instanceof Expense
            ? $this->presentExpense($model->refresh())
            : $this->presentTransaction($model->refresh());
    }

    /**
     * Do'kon sozlamasi. O'zgarganda avtomatik yozuvlar qayta quriladi —
     * tugma bosilgach natija darhol ko'rinishi kerak.
     *
     * @param  array<string,bool>  $settings
     * @return array<string,bool>
     */
    public function updateSettings(Shop $shop, array $settings, CashMirrorService $mirror): array
    {
        $shop->update(array_filter([
            'cash_track_production' => $settings['track_production'] ?? null,
            'cash_track_returns' => $settings['track_returns'] ?? null,
        ], static fn ($v) => $v !== null));

        $mirror->resyncShop($shop->refresh());

        return $this->settings($shop);
    }

    /** @return array<string,bool> */
    public function settings(Shop $shop): array
    {
        return [
            'track_production' => (bool) $shop->cash_track_production,
            'track_returns' => (bool) $shop->cash_track_returns,
        ];
    }

    // ─── Ichki so'rovlar ─────────────────────────────────────────────────

    /** @return Collection<int,object> */
    private function incomeRows(Shop $shop, string $from, string $to)
    {
        return $this->cashQuery($shop, $from, $to)
            ->where('type', CashTransactionType::Income->value)
            ->get();
    }

    /** @return Collection<int,object> */
    private function expenseRows(Shop $shop, string $from, string $to)
    {
        $cash = $this->cashQuery($shop, $from, $to)
            ->where('type', CashTransactionType::Expense->value)
            ->get();

        return $this->expenseQuery($shop, $from, $to)->get()->concat($cash);
    }

    /**
     * Ikkala jadval bir xil ustun to'plamini qaytarishi shart — aks holda
     * UNION ustunlarni aralashtirib yuboradi.
     */
    private function expenseQuery(Shop $shop, string $from, string $to): Builder
    {
        return DB::table('expenses')
            ->where('shop_id', $shop->id)
            ->whereBetween('date', $this->dayBounds($from, $to))
            ->select([
                'id',
                DB::raw("'".CashTransactionType::Expense->value."' as type"),
                DB::raw("'".CashTransactionSource::Manual->value."' as source"),
                'category',
                'amount',
                'description',
                'date',
                'created_at',
            ]);
    }

    private function cashQuery(Shop $shop, string $from, string $to): Builder
    {
        return DB::table('cash_transactions')
            ->where('shop_id', $shop->id)
            ->whereBetween('date', $this->dayBounds($from, $to))
            ->select([
                'id',
                'type',
                'source',
                'category',
                'amount',
                'description',
                'date',
                'created_at',
            ]);
    }

    /**
     * `date` ustuni bazaga vaqt bilan yoziladi ("2026-08-10 00:00:00"), shuning
     * uchun yalang'och sana bilan solishtirish oxirgi kunni tashlab ketardi.
     *
     * @return array{0:string,1:string}
     */
    private function dayBounds(string $from, string $to): array
    {
        return [
            CarbonImmutable::parse($from)->startOfDay()->toDateTimeString(),
            CarbonImmutable::parse($to)->endOfDay()->toDateTimeString(),
        ];
    }

    /**
     * @param  Collection<int,object>  $rows
     * @return array<string,float>|\stdClass
     */
    private function byCategory($rows): array|\stdClass
    {
        $grouped = $rows->groupBy(fn ($row) => $row->category ?: 'boshqa')
            ->map(fn ($group) => round((float) $group->sum('amount'), 2))
            ->sortDesc()
            ->toArray();

        // Bo'sh massiv JSON'da `[]` bo'lib ketadi — Dart tomonda `Map` parse
        // xatosi bermasin.
        return $grouped === [] ? new \stdClass : $grouped;
    }

    private function presentExpense(Expense $expense): object
    {
        return (object) [
            'id' => $expense->id,
            'type' => CashTransactionType::Expense->value,
            'source' => CashTransactionSource::Manual->value,
            'category' => $expense->category,
            'amount' => $expense->amount,
            'description' => $expense->description,
            'date' => $expense->date?->toDateString(),
            'created_at' => $expense->created_at?->toIso8601String(),
        ];
    }

    private function presentTransaction(CashTransaction $transaction): object
    {
        return (object) [
            'id' => $transaction->id,
            'type' => $transaction->type->value,
            'source' => $transaction->source->value,
            'category' => $transaction->category,
            'amount' => $transaction->amount,
            'description' => $transaction->description,
            'date' => $transaction->date?->toDateString(),
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }
}
