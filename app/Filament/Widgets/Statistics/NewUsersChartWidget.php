<?php

namespace App\Filament\Widgets\Statistics;

use App\Filament\Widgets\Statistics\Concerns\HasPeriodFilter;
use App\Services\Admin\UserStatisticsService;
use Filament\Widgets\ChartWidget;

class NewUsersChartWidget extends ChartWidget
{
    use HasPeriodFilter;

    public ?string $filter = UserStatisticsService::DAILY;

    protected static ?string $heading = 'Yangi foydalanuvchilar';

    protected static ?string $maxHeight = '360px';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $series = $this->statistics()->newUsers($this->period(), $this->from(), $this->to());

        return [
            'datasets' => [
                [
                    'label' => 'Yangi foydalanuvchilar',
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
