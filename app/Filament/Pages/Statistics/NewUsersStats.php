<?php

namespace App\Filament\Pages\Statistics;

use App\Filament\Widgets\Statistics\NewUsersChartWidget;
use App\Filament\Widgets\Statistics\NewUsersOverviewWidget;

class NewUsersStats extends StatisticsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'Yangi foydalanuvchilar';

    protected static ?string $title = 'Yangi foydalanuvchilar';

    protected static ?int $navigationSort = 1;

    protected static string $routePath = 'statistika/yangi-foydalanuvchilar';

    public function getSubheading(): string
    {
        return 'Tanlangan davrda ro\'yxatdan o\'tganlar. Keyinchalik o\'chirilgan hisoblar ham sanaladi.';
    }

    public function getWidgets(): array
    {
        return [
            NewUsersOverviewWidget::class,
            NewUsersChartWidget::class,
        ];
    }
}
