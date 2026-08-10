<?php

namespace App\Filament\Pages\Statistics;

use App\Filament\Widgets\Statistics\NewUsersChartWidget;

class NewUsersStats extends StatisticsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'Yangi foydalanuvchilar';

    protected static ?string $title = 'Yangi foydalanuvchilar';

    protected static ?int $navigationSort = 1;

    protected static string $routePath = 'statistika/yangi-foydalanuvchilar';

    public function getWidgets(): array
    {
        return [NewUsersChartWidget::class];
    }
}
