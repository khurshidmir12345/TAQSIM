<?php

namespace App\Filament\Pages\Statistics;

use App\Filament\Widgets\Statistics\ConfiguredNotStartedChartWidget;
use App\Filament\Widgets\Statistics\ConfiguredNotStartedOverviewWidget;
use App\Filament\Widgets\Statistics\ConfiguredNotStartedTableWidget;

class ConfiguredNotStartedStats extends StatisticsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Sozlagan, ishlamagan';

    protected static ?string $title = 'Sozlagan, lekin ishlamaganlar';

    protected static ?int $navigationSort = 3;

    protected static string $routePath = 'statistika/sozlagan-ishlamagan';

    public function getSubheading(): string
    {
        return 'Mahsulot, xom ashyo yoki retsept kiritilgan, ammo birorta kirim, vozvrat, xarajat va zakaz yo\'q.';
    }

    public function getWidgets(): array
    {
        return [
            ConfiguredNotStartedOverviewWidget::class,
            ConfiguredNotStartedChartWidget::class,
            ConfiguredNotStartedTableWidget::class,
        ];
    }
}
