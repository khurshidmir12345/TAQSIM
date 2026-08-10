<?php

namespace App\Services\Admin;

use App\Models\BreadReturn;
use App\Models\Production;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admin paneldagi foydalanuvchi statistikasi.
 *
 * Baza UTC'da saqlaydi, admin esa mahalliy kun kesimini kutadi — shuning
 * uchun barcha sana chegaralari `app.business_timezone` bo'yicha quriladi.
 * Aks holda kechqurun ro'yxatdan o'tgan foydalanuvchi ertangi kunga tushib
 * qolardi.
 */
final class UserStatisticsService
{
    public const DAILY = 'daily';

    public const MONTHLY = 'monthly';

    /**
     * Davr uchun bo'sh kataklar (grafik uzluksiz bo'lishi uchun).
     *
     * @return array<int,string>
     */
    public function buckets(string $period, string $from, string $to): array
    {
        $tz = $this->timezone();
        $cursor = CarbonImmutable::parse($from, $tz)->startOfDay();
        $end = CarbonImmutable::parse($to, $tz)->endOfDay();

        $keys = [];

        if ($period === self::MONTHLY) {
            $cursor = $cursor->startOfMonth();

            while ($cursor <= $end) {
                $keys[] = $cursor->format('Y-m');
                $cursor = $cursor->addMonth();
            }

            return $keys;
        }

        while ($cursor <= $end) {
            $keys[] = $cursor->format('Y-m-d');
            $cursor = $cursor->addDay();
        }

        return $keys;
    }

    /**
     * Yangi ro'yxatdan o'tganlar.
     *
     * Keyinchalik o'chirilgan hisoblar ham sanaladi — bu jalb qilish
     * ko'rsatkichi, o'sha kuni ro'yxatdan o'tgan odam soni o'zgarmasligi kerak.
     *
     * @return array<string,int>
     */
    public function newUsers(string $period, string $from, string $to): array
    {
        $timestamps = User::query()
            ->withTrashed()
            ->whereBetween('created_at', $this->range($from, $to))
            ->pluck('created_at');

        return $this->tally($timestamps, $period, $from, $to);
    }

    /**
     * Faol foydalanuvchilar — kirim (ishlab chiqarish) yoki vozvrat yozuvini
     * YARATGANLAR. Yozuvning biznes sanasi emas, yaratilgan vaqti olinadi:
     * kechagi ma'lumotni bugun kiritgan odam bugun faol hisoblanadi.
     *
     * Bir katakda bir foydalanuvchi bir marta sanaladi.
     *
     * @return array<string,int>
     */
    public function activeUsers(string $period, string $from, string $to): array
    {
        $range = $this->range($from, $to);
        $tz = $this->timezone();

        /** @var array<string,array<string,true>> $seen */
        $seen = array_fill_keys($this->buckets($period, $from, $to), []);

        foreach ([Production::class, BreadReturn::class] as $model) {
            $rows = $model::query()
                ->whereBetween('created_at', $range)
                ->whereNotNull('created_by')
                ->get(['created_by', 'created_at']);

            foreach ($rows as $row) {
                $key = $this->bucketOf($row->created_at, $period, $tz);

                if (array_key_exists($key, $seen)) {
                    $seen[$key][(string) $row->created_by] = true;
                }
            }
        }

        return array_map(count(...), $seen);
    }

    /**
     * Sozlagan, lekin ishni boshlamagan do'konlar.
     *
     * Ya'ni mahsulot / xom ashyo / retsept kiritilgan (kalkulyatsiya qilingan),
     * ammo birorta ham kirim, vozvrat, xarajat yoki zakaz yozuvi yo'q —
     * ilova sinab ko'rilgan, lekin haqiqiy ishda ishlatilmagan.
     *
     * @param  string|null  $from  berilsa, do'kon shu oraliqda ochilgan bo'lishi kerak
     * @return Builder<Shop>
     */
    public function configuredNotStartedQuery(?string $from = null, ?string $to = null): Builder
    {
        $query = Shop::query()
            ->where(function (Builder $q): void {
                $q->has('recipes')
                    ->orHas('ingredients')
                    ->orHas('breadCategories');
            })
            ->doesntHave('productions')
            ->doesntHave('breadReturns')
            ->doesntHave('expenses')
            ->doesntHave('customerOrders');

        if ($from !== null && $to !== null) {
            $query->whereBetween('created_at', $this->range($from, $to));
        }

        return $query;
    }

    /**
     * Shu holatdagi do'konlar yaratilgan davr bo'yicha taqsimlanishi —
     * "qaysi oyda kelganlar ishni boshlamadi" degan savolga javob.
     *
     * @return array<string,int>
     */
    public function configuredNotStarted(string $period, string $from, string $to): array
    {
        $timestamps = $this->configuredNotStartedQuery($from, $to)->pluck('created_at');

        return $this->tally($timestamps, $period, $from, $to);
    }

    /**
     * Vaqt belgilarini kataklarga taqsimlaydi.
     *
     * @param  iterable<mixed>  $timestamps
     * @return array<string,int>
     */
    private function tally(iterable $timestamps, string $period, string $from, string $to): array
    {
        $tz = $this->timezone();
        $counts = array_fill_keys($this->buckets($period, $from, $to), 0);

        foreach ($timestamps as $timestamp) {
            if ($timestamp === null) {
                continue;
            }

            $key = $this->bucketOf($timestamp, $period, $tz);

            if (array_key_exists($key, $counts)) {
                $counts[$key]++;
            }
        }

        return $counts;
    }

    private function bucketOf(\DateTimeInterface $moment, string $period, string $tz): string
    {
        $local = CarbonImmutable::instance($moment)->setTimezone($tz);

        return $period === self::MONTHLY ? $local->format('Y-m') : $local->format('Y-m-d');
    }

    /**
     * Mahalliy kun chegaralarini UTC oralig'iga aylantiradi.
     *
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    private function range(string $from, string $to): array
    {
        $tz = $this->timezone();

        return [
            CarbonImmutable::parse($from, $tz)->startOfDay()->utc(),
            CarbonImmutable::parse($to, $tz)->endOfDay()->utc(),
        ];
    }

    private function timezone(): string
    {
        return (string) config('app.business_timezone');
    }
}
