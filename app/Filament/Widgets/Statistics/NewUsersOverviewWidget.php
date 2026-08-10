<?php

namespace App\Filament\Widgets\Statistics;

use App\Filament\Widgets\Statistics\Concerns\ReadsStatisticsFilters;
use App\Models\User;
use App\Services\Admin\UserStatisticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NewUsersOverviewWidget extends BaseWidget
{
    use ReadsStatisticsFilters;

    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $series = $this->statistics()->newUsers($this->period(), $this->from(), $this->to());

        $inPeriod = array_sum($series);
        $buckets = max(1, count($series));
        $isMonthly = $this->period() === UserStatisticsService::MONTHLY;

        $peakValue = $series === [] ? 0 : max($series);
        $peakBucket = array_search($peakValue, $series, true);

        return [
            Stat::make('Davr ichida ro\'yxatdan o\'tgan', number_format($inPeriod))
                ->description($isMonthly ? "{$buckets} oy kesimida" : "{$buckets} kun kesimida")
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info')
                ->chart($this->sparkline($series)),

            Stat::make('O\'rtacha', number_format($inPeriod / $buckets, 1))
                ->description($isMonthly ? 'har oyda' : 'har kuni')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),

            Stat::make('Eng yuqori', number_format($peakValue))
                ->description($peakBucket ? $this->label((string) $peakBucket) : '—')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Jami foydalanuvchilar', number_format(User::query()->count()))
                ->description($this->deletedNote())
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),
        ];
    }

    private function deletedNote(): string
    {
        $deleted = $this->statistics()->deletedUsers($this->from(), $this->to());

        return $deleted > 0
            ? "davrda {$deleted} ta hisob o'chirilgan"
            : 'hozirda faol hisoblar';
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
