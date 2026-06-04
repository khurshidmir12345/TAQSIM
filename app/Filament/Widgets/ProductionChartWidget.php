<?php

namespace App\Filament\Widgets;

use App\Models\Production;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ProductionChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Ishlab chiqarish (oxirgi 14 kun)';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $labels = [];
        $values = [];

        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $labels[] = $date->format('d.m');
            $values[] = (int) Production::query()
                ->whereDate('date', $date->toDateString())
                ->sum('bread_produced');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ishlab chiqarilgan non (dona)',
                    'data' => $values,
                    'borderColor' => '#00A896',
                    'backgroundColor' => 'rgba(0, 168, 150, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
