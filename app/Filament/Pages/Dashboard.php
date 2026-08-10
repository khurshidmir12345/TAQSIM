<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LatestShopsWidget;
use App\Filament\Widgets\LatestUsersWidget;
use App\Filament\Widgets\ProductionChartWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\UsersChartWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Umumiy ko'rinish — kunlik nazorat uchun qisqa xulosa.
 *
 * Vidjetlar ro'yxati ataylab aniq sanab o'tilgan: panel `discoverWidgets`
 * bilan barcha vidjetlarni topadi, ro'yxat bo'lmasa statistika sahifalarining
 * vidjetlari ham shu yerga tushib, dashboard aralashib ketardi.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Umumiy ko\'rinish';

    protected static ?string $title = 'Umumiy ko\'rinish';

    protected static ?int $navigationSort = -2;

    public function getWidgets(): array
    {
        return [
            StatsOverviewWidget::class,
            ProductionChartWidget::class,
            UsersChartWidget::class,
            LatestShopsWidget::class,
            LatestUsersWidget::class,
        ];
    }
}
