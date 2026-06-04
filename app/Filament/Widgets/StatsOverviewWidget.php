<?php

namespace App\Filament\Widgets;

use App\Models\BreadReturn;
use App\Models\Expense;
use App\Models\Production;
use App\Models\Shop;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth();

        $usersTotal = User::query()->count();
        $usersThisMonth = User::query()->where('created_at', '>=', $monthStart)->count();

        $shopsTotal = Shop::query()->count();
        $shopsActive = Shop::query()->where('is_active', true)->count();

        $breadToday = (int) Production::query()->whereDate('date', $today)->sum('bread_produced');
        $breadMonth = (int) Production::query()->where('date', '>=', $monthStart)->sum('bread_produced');

        $expensesMonth = (float) Expense::query()->where('date', '>=', $monthStart)->sum('amount');
        $returnsMonth = (int) BreadReturn::query()->where('date', '>=', $monthStart)->sum('quantity');

        return [
            Stat::make('Foydalanuvchilar', number_format($usersTotal))
                ->description("+{$usersThisMonth} shu oy")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info')
                ->chart($this->usersTrend()),

            Stat::make('Do\'konlar', number_format($shopsTotal))
                ->description("{$shopsActive} ta faol")
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('primary'),

            Stat::make('Bugungi ishlab chiqarish', number_format($breadToday) . ' dona')
                ->description(number_format($breadMonth) . ' dona shu oy')
                ->descriptionIcon('heroicon-m-cake')
                ->color('success'),

            Stat::make('Oylik xarajatlar', number_format($expensesMonth, 0, '.', ' '))
                ->description(number_format($returnsMonth) . ' dona qaytarilgan non')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
        ];
    }

    /**
     * Oxirgi 7 kunda kunlik yangi foydalanuvchilar (sparkline uchun).
     *
     * @return array<int, int>
     */
    private function usersTrend(): array
    {
        $trend = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $trend[] = User::query()->whereDate('created_at', $day)->count();
        }

        return $trend;
    }
}
