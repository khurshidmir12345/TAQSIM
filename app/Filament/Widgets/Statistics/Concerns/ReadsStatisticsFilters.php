<?php

namespace App\Filament\Widgets\Statistics\Concerns;

use App\Filament\Pages\Statistics\StatisticsPage;
use App\Services\Admin\UserStatisticsService;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Sahifadagi filtr qiymatlarini vidjetga xavfsiz yetkazadi.
 *
 * Filtr hali to'ldirilmagan bo'lsa standart oraliq ishlatiladi — vidjet
 * birinchi renderdayoq bo'sh emas, ma'noli ko'rinadi.
 */
trait ReadsStatisticsFilters
{
    use InteractsWithPageFilters;

    protected function period(): string
    {
        return ($this->filters['period'] ?? null) === UserStatisticsService::MONTHLY
            ? UserStatisticsService::MONTHLY
            : UserStatisticsService::DAILY;
    }

    protected function from(): string
    {
        return $this->date('from', StatisticsPage::resolveRange('30d')[0]);
    }

    protected function to(): string
    {
        return $this->date('to', StatisticsPage::resolveRange('30d')[1]);
    }

    protected function statistics(): UserStatisticsService
    {
        return app(UserStatisticsService::class);
    }

    /** Grafik o'qi uchun o'qiladigan yorliq: "05 avg" yoki "avg 2026". */
    protected function label(string $bucket): string
    {
        $isMonthly = $this->period() === UserStatisticsService::MONTHLY;

        $date = CarbonImmutable::createFromFormat(
            $isMonthly ? 'Y-m' : 'Y-m-d',
            $bucket,
            config('app.business_timezone'),
        );

        return $date->translatedFormat($isMonthly ? 'M Y' : 'd M');
    }

    /** Davr necha kunlik — o'rtachani hisoblash uchun. */
    protected function dayCount(): int
    {
        $tz = config('app.business_timezone');

        return CarbonImmutable::parse($this->from(), $tz)
            ->startOfDay()
            ->diffInDays(CarbonImmutable::parse($this->to(), $tz)->startOfDay()) + 1;
    }

    private function date(string $key, string $fallback): string
    {
        $value = $this->filters[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return $fallback;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
