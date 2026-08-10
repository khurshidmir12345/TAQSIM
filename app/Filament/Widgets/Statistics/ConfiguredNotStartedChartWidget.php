<?php

namespace App\Filament\Widgets\Statistics;

use App\Filament\Widgets\Statistics\Concerns\ReadsStatisticsFilters;
use App\Services\Admin\UserStatisticsService;
use Filament\Widgets\ChartWidget;

class ConfiguredNotStartedChartWidget extends ChartWidget
{
    use ReadsStatisticsFilters;

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '320px';

    public function getHeading(): string
    {
        return $this->period() === UserStatisticsService::MONTHLY
            ? 'Sozlab, boshlamaganlar — oylik'
            : 'Sozlab, boshlamaganlar — kunlik';
    }

    public function getDescription(): string
    {
        return 'Do\'kon ochilgan sana bo\'yicha. Ustun baland bo\'lsa — o\'sha davrda kelganlar ishga tushmagan.';
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
