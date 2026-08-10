<?php

namespace App\Providers;

use App\Models\BreadReturn;
use App\Models\Production;
use App\Observers\BreadReturnObserver;
use App\Observers\ProductionObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Asosiy sahifadagi amallar kassaga o'zi ko'chirilsin — chiqim va
        // vozvrat API'dan ham, admin paneldan ham o'zgaradi.
        Production::observe(ProductionObserver::class);
        BreadReturn::observe(BreadReturnObserver::class);

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('telegram-session', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('telegram-check', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->ip().'|'.$request->route('sessionToken')
            );
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
