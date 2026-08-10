<?php

namespace App\Filament\Widgets\Statistics;

use App\Filament\Widgets\Statistics\Concerns\ReadsStatisticsFilters;
use App\Models\Shop;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Sozlab qo'ygan, lekin ishni boshlamagan do'konlar — mahsulot / xom ashyo /
 * retsept kiritilgan, ammo birorta kirim, vozvrat, xarajat yoki zakaz yo'q.
 */
class ConfiguredNotStartedOverviewWidget extends BaseWidget
{
    use ReadsStatisticsFilters;

    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $series = $this->statistics()->configuredNotStarted($this->period(), $this->from(), $this->to());

        $inPeriod = array_sum($series);
        $createdInPeriod = $this->statistics()->shopsCreated($this->from(), $this->to());
        $shareInPeriod = $createdInPeriod > 0
            ? round($inPeriod / $createdInPeriod * 100, 1)
            : 0.0;

        $allTime = $this->statistics()->configuredNotStartedOwners();
        $shopsTotal = Shop::query()->count();

        return [
            Stat::make('Davrda sozlab, boshlamaganlar', number_format($inPeriod))
                ->description("shu davrda ochilgan {$createdInPeriod} ta do'kondan")
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color($shareInPeriod >= 50 ? 'danger' : 'warning')
                ->chart($this->sparkline($series)),

            Stat::make('Ulushi', $shareInPeriod.'%')
                ->description('davrda ochilgan do\'konlardan')
                ->descriptionIcon('heroicon-m-chart-pie')
                ->color($shareInPeriod >= 50 ? 'danger' : 'warning'),

            Stat::make('Jami (butun davr)', number_format($allTime))
                ->description('hech qachon ishlatilmagan')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Barcha do\'konlar', number_format($shopsTotal))
                ->description($shopsTotal > 0
                    ? round($allTime / $shopsTotal * 100, 1).'% ishga tushmagan'
                    : '—')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('gray'),
        ];
    }

    /**
     * @param  array<string,int>  $series
     * @return array<int,int>
     */
    private function sparkline(array $series): array
    {
        return array_slice(array_values($series), -12);
    }
}
