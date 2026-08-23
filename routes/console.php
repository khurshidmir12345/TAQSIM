<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Rejalashtirilgan bildirishnomalar
|--------------------------------------------------------------------------
|
| Ilova UTC'da ishlaydi, foydalanuvchi esa mahalliy vaqtda — shuning uchun
| vaqt mintaqasi aniq ko'rsatilgan. Komandalar ishni navbatga qo'yadi,
| yuborishning o'zi queue worker'da bo'ladi.
|
*/

// Nonvoyxonalar tunda ishlaydi — zakaz eslatmasi smena boshlanishidan oldin.
Schedule::command('notifications:order-reminder')
    ->dailyAt('03:30')
    ->timezone(config('app.business_timezone'))
    ->withoutOverlapping();

// Muddat tugashi haqida Telegram orqali ogohlantirish (7 / 3 / 1 kun va
// tugagan kun). Ilova ichida bu haqda gapirilmaydi — do'kon qoidalari.
Schedule::command('access:notices')
    ->dailyAt('10:00')
    ->timezone(config('app.business_timezone'))
    ->withoutOverlapping();

Schedule::command('notifications:daily-greeting')
    ->dailyAt('07:00')
    ->timezone(config('app.business_timezone'))
    ->withoutOverlapping();
