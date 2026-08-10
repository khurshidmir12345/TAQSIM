<?php

namespace App\Filament\Widgets\Statistics\Concerns;

use App\Services\Admin\UserStatisticsService;
use Carbon\CarbonImmutable;

/**
 * Grafik ustidagi yagona tanlov: kunlik yoki oylik.
 *
 * Kunlik — oxirgi 30 kun, oylik — oxirgi 12 oy. Boshqa sozlama yo'q:
 * grafik ochilishi bilan ma'noli ko'rinishi kerak.
 */
trait HasPeriodFilter
{
    protected function getFilters(): ?array
    {
        return [
            UserStatisticsService::DAILY => 'Kunlik',
            UserStatisticsService::MONTHLY => 'Oylik',
        ];
    }

    protected function period(): string
    {
        return $this->filter === UserStatisticsService::MONTHLY
            ? UserStatisticsService::MONTHLY
            : UserStatisticsService::DAILY;
    }

    protected function from(): string
    {
        $today = $this->today();

        return $this->period() === UserStatisticsService::MONTHLY
            ? $today->subMonths(11)->startOfMonth()->toDateString()
            : $today->subDays(29)->toDateString();
    }

    protected function to(): string
    {
        return $this->today()->toDateString();
    }

    /** Grafik o'qi uchun yorliq: "05 avg" yoki "avg 2026". */
    protected function label(string $bucket): string
    {
        $isMonthly = $this->period() === UserStatisticsService::MONTHLY;

        return CarbonImmutable::createFromFormat(
            $isMonthly ? 'Y-m' : 'Y-m-d',
            $bucket,
            config('app.business_timezone'),
        )->translatedFormat($isMonthly ? 'M Y' : 'd M');
    }

    protected function statistics(): UserStatisticsService
    {
        return app(UserStatisticsService::class);
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.business_timezone'))->startOfDay();
    }
}
