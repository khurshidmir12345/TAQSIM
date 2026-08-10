<?php

namespace App\Filament\Widgets\Statistics;

use App\Filament\Widgets\Statistics\Concerns\ReadsStatisticsFilters;
use App\Models\User;
use App\Services\Admin\UserStatisticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Faollik = davr ichida kirim (ishlab chiqarish) yoki vozvrat yozuvini
 * yaratgan foydalanuvchi.
 */
class ActiveUsersOverviewWidget extends BaseWidget
{
    use ReadsStatisticsFilters;

    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $series = $this->statistics()->activeUsers($this->period(), $this->from(), $this->to());
        $unique = $this->statistics()->activeUsersTotal($this->from(), $this->to());

        $buckets = max(1, count($series));
        $isMonthly = $this->period() === UserStatisticsService::MONTHLY;

        $peakValue = $series === [] ? 0 : max($series);
        $peakBucket = array_search($peakValue, $series, true);

        $totalUsers = User::query()->count();
        $share = $totalUsers > 0 ? round($unique / $totalUsers * 100, 1) : 0.0;

        return [
            Stat::make('Noyob faol foydalanuvchi', number_format($unique))
                ->description('davr davomida kamida bir marta')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('success')
                ->chart($this->sparkline($series)),

            Stat::make('O\'rtacha', number_format(array_sum($series) / $buckets, 1))
                ->description($isMonthly ? 'har oyda faol' : 'har kuni faol')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info'),

            Stat::make('Eng yuqori', number_format($peakValue))
                ->description($peakBucket ? $this->label((string) $peakBucket) : '—')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),

            Stat::make('Faollik ulushi', $share.'%')
                ->description('jami '.number_format($totalUsers).' foydalanuvchidan')
                ->descriptionIcon('heroicon-m-users')
                ->color($share >= 30 ? 'success' : ($share >= 10 ? 'warning' : 'danger')),
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
