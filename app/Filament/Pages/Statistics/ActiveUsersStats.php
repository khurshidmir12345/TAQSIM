<?php

namespace App\Filament\Pages\Statistics;

use App\Filament\Widgets\Statistics\ActiveUsersChartWidget;
use App\Filament\Widgets\Statistics\ActiveUsersOverviewWidget;

class ActiveUsersStats extends StatisticsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationLabel = 'Faol foydalanuvchilar';

    protected static ?string $title = 'Faol foydalanuvchilar';

    protected static ?int $navigationSort = 2;

    protected static string $routePath = 'statistika/faol-foydalanuvchilar';

    public function getSubheading(): string
    {
        return 'Faollik kirim (ishlab chiqarish) yoki vozvrat yozuvi yaratilganidan aniqlanadi.';
    }

    public function getWidgets(): array
    {
        return [
            ActiveUsersOverviewWidget::class,
            ActiveUsersChartWidget::class,
        ];
    }
}
