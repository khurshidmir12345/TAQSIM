<?php

namespace App\Filament\Widgets\Statistics;

use App\Filament\Widgets\Statistics\Concerns\ReadsStatisticsFilters;
use App\Services\Admin\UserStatisticsService;
use Filament\Widgets\ChartWidget;

class NewUsersChartWidget extends ChartWidget
{
    use ReadsStatisticsFilters;

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '320px';

    public function getHeading(): string
    {
        return $this->period() === UserStatisticsService::MONTHLY
            ? 'Yangi foydalanuvchilar — oylik'
            : 'Yangi foydalanuvchilar — kunlik';
    }

    protected function getData(): array
    {
        $series = $this->statistics()->newUsers($this->period(), $this->from(), $this->to());

        return [
            'datasets' => [
                [
                    'label' => 'Ro\'yxatdan o\'tganlar',
                    'data' => array_values($series),
                    'backgroundColor' => '#0B3C5D',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => array_map($this->label(...), array_keys($series)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
            'plugins' => ['legend' => ['display' => false]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
