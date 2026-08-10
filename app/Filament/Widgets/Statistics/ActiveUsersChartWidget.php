<?php

namespace App\Filament\Widgets\Statistics;

use App\Filament\Widgets\Statistics\Concerns\ReadsStatisticsFilters;
use App\Services\Admin\UserStatisticsService;
use Filament\Widgets\ChartWidget;

class ActiveUsersChartWidget extends ChartWidget
{
    use ReadsStatisticsFilters;

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '320px';

    public function getHeading(): string
    {
        return $this->period() === UserStatisticsService::MONTHLY
            ? 'Faol foydalanuvchilar — oylik'
            : 'Faol foydalanuvchilar — kunlik';
    }

    public function getDescription(): string
    {
        return 'Kirim yoki vozvrat yozuvini yaratganlar. Bir katakda bir foydalanuvchi bir marta sanaladi.';
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
