<?php

namespace App\Filament\Widgets\Statistics;

use App\Filament\Widgets\Statistics\Concerns\HasPeriodFilter;
use App\Services\Admin\UserStatisticsService;
use Filament\Widgets\ChartWidget;

class ActiveUsersChartWidget extends ChartWidget
{
    use HasPeriodFilter;

    public ?string $filter = UserStatisticsService::DAILY;

    protected static ?string $heading = 'Faol foydalanuvchilar';

    protected static ?string $maxHeight = '360px';

    protected int|string|array $columnSpan = 'full';

    public function getDescription(): string
    {
        return 'Kirim yoki vozvrat yozuvini yaratganlar.';
    }

    protected function getData(): array
    {
        $series = $this->statistics()->activeUsers($this->period(), $this->from(), $this->to());

        return [
            'datasets' => [
                [
                    'label' => 'Faol foydalanuvchilar',
                    'data' => array_values($series),
                    'borderColor' => '#00A896',
                    'backgroundColor' => 'rgba(0, 168, 150, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 2,
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
        return 'line';
    }
}
