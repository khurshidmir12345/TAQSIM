<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

class UsersChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Yangi foydalanuvchilar (oxirgi 6 oy)';

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $labels = [];
        $values = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i)->startOfMonth();
            $labels[] = $month->translatedFormat('M Y');
            $values[] = User::query()
                ->whereBetween('created_at', [$month, (clone $month)->endOfMonth()])
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Yangi foydalanuvchilar',
                    'data' => $values,
                    'backgroundColor' => '#0B3C5D',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
