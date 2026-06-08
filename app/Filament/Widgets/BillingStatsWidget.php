<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Subscription;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BillingStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();

        $activePaid = Subscription::query()
            ->where('is_current', true)
            ->whereNull('cancelled_at')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>=', $now)
            ->whereHas('plan', fn ($q) => $q->where('is_trial', false))
            ->count();

        $trialing = Subscription::query()
            ->where('is_current', true)
            ->whereNull('cancelled_at')
            ->where('trial_ends_at', '>=', $now)
            ->whereHas('plan', fn ($q) => $q->where('is_trial', true))
            ->count();

        $pendingTopups = Order::query()
            ->where('type', 'topup')
            ->where('status', 'pending')
            ->count();

        $revenueMonth = (float) Order::query()
            ->where('type', 'subscription')
            ->where('status', 'paid')
            ->where('paid_at', '>=', $monthStart)
            ->sum('amount_local');

        return [
            Stat::make('Faol obunachilar', number_format($activePaid))
                ->description('Pulli tariflar')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('success'),

            Stat::make('Trial', number_format($trialing))
                ->description('Bepul sinov davrida')
                ->descriptionIcon('heroicon-m-gift')
                ->color('info'),

            Stat::make('Oylik tushum', number_format($revenueMonth, 0, '.', ' ') . ' UZS')
                ->description('Obuna to\'lovlari (shu oy)')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Kutilayotgan to\'ldirishlar', number_format($pendingTopups))
                ->description('Tasdiq kutilmoqda')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingTopups > 0 ? 'warning' : 'gray'),
        ];
    }
}
