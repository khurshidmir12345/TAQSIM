<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// USD→UZS kursini har kuni yangilab turish.
Schedule::command('billing:sync-exchange')->dailyAt('07:00');

// Pulli xodim o'rinlarini har kuni tekshirib, muddati kelganini yangilash.
Schedule::command('employees:renew-seats')->dailyAt('08:00');
