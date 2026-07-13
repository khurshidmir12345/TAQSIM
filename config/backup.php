<?php

return [

    /*
    |--------------------------------------------------------------------------
    | mysqldump binary yo'li
    |--------------------------------------------------------------------------
    |
    | Ba'zi serverlarda `mysqldump` PATH da bo'lmasligi mumkin. Bunday holda
    | to'liq yo'lni .env orqali berish mumkin (masalan: /usr/bin/mysqldump).
    | Agar binary topilmasa, tizim avtomatik PHP orqali zaxira oladi.
    |
    */

    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),

    /*
    | Zaxira fayllari vaqtincha saqlanadigan papka (storage ichida).
    */
    'directory' => 'backups',

    /*
    | mysqldump uchun maksimal ishlash vaqti (soniya).
    */
    'timeout' => (int) env('BACKUP_TIMEOUT', 600),

];
