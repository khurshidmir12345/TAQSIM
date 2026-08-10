<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Statistics\ActiveUsersChartWidget;
use App\Filament\Widgets\Statistics\ConfiguredNotStartedChartWidget;
use App\Filament\Widgets\Statistics\NewUsersChartWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Foydalanuvchi statistikasi — barcha grafik bitta sahifada, ketma-ket.
 *
 * Sahifa darajasida filtr yo'q: davrni har bir grafikning o'zidagi
 * "Kunlik / Oylik" tanlovi belgilaydi.
 */
class Statistics extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Statistika';

    protected static ?string $title = 'Statistika';

    protected static ?int $navigationSort = -1;

    protected static string $routePath = 'statistika';

    public function getWidgets(): array
    {
        return [
            NewUsersChartWidget::class,
            ActiveUsersChartWidget::class,
            ConfiguredNotStartedChartWidget::class,
        ];
    }

    /** Grafiklar bir ustunda, to'liq kenglikda. */
    public function getColumns(): int|string|array
    {
        return 1;
    }
}
