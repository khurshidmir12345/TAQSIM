<?php

namespace App\Filament\Pages\Statistics;

use App\Filament\Widgets\Statistics\ConfiguredNotStartedChartWidget;

class ConfiguredNotStartedStats extends StatisticsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Sozlagan, ishlamagan';

    protected static ?string $title = 'Sozlagan, lekin ishlamaganlar';

    protected static ?int $navigationSort = 3;

    protected static string $routePath = 'statistika/sozlagan-ishlamagan';

    public function getWidgets(): array
    {
        return [ConfiguredNotStartedChartWidget::class];
    }
}
