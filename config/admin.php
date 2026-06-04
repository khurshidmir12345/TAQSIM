<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Filament Admin Panel — yagona admin hisob ma'lumotlari
    |--------------------------------------------------------------------------
    |
    | Panelga faqat shu email egasi kira oladi. Login/parol .env faylda
    | saqlanadi va `AdminUserSeeder` orqali bazaga yoziladi.
    |
    */

    'email' => env('FILAMENT_ADMIN_EMAIL', 'admin@taqseem.uz'),

    'password' => env('FILAMENT_ADMIN_PASSWORD', 'password'),

    'name' => env('FILAMENT_ADMIN_NAME', 'Administrator'),

];
