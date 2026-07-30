<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Filament Admin Panel — yagona admin hisob ma'lumotlari
    |--------------------------------------------------------------------------
    |
    | Panelga faqat shu email egasi kira oladi. Login/parol .env faylda
    | saqlanadi. `AdminUserSeeder` faqat mavjud bo'lmagan adminni yaratadi.
    |
    */

    'email' => env('FILAMENT_ADMIN_EMAIL'),

    'password' => env('FILAMENT_ADMIN_PASSWORD'),

    'name' => env('FILAMENT_ADMIN_NAME', 'Administrator'),

];
