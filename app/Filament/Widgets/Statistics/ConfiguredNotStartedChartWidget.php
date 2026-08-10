<?php

namespace App\Filament\Widgets\Statistics;

use App\Filament\Widgets\Statistics\Concerns\HasPeriodFilter;
use App\Services\Admin\UserStatisticsService;
use Filament\Widgets\ChartWidget;

class ConfiguredNotStartedChartWidget extends ChartWidget
{
    use HasPeriodFilter;

    public ?string $filter = UserStatisticsService::DAILY;

    protected static ?string $heading = 'Sozlagan, lekin ishlamaganlar';

    protected static ?string $maxHeight = '360px';

    protected int|string|array $columnSpan = 'full';

    public function getDescription(): string
    {
        return 'Mahsulot, xom ashyo yoki retsept kiritgan, ammo birorta kirim, vozvrat, xarajat va zakaz yo\'q. Do\'kon ochilgan sana bo\'yicha.';
    }

    protected function getData(): array
    {
        $series = $this->statistics()->configuredNotStarted($this->period(), $this->from(), $this->to());

        return [
            'datasets' => [
                [
                    'label' => 'Sozlagan, ishlatmagan',
                    'data' => array_values($series),
                    'backgroundColor' => '#F59E0B',
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
