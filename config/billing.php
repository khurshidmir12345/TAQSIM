<?php

return [
    // Trial tugagandan keyin yumshoq (read-only) davr, kunlarda.
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 3),

    // Standart bepul trial uzunligi (kun). Trial tarifning trial_days qiymati ustun.
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 30),

    // Kurs jadvali bo'sh bo'lsa ishlatiladigan zaxira USD→UZS kursi.
    'default_usd_uzs' => (float) env('BILLING_DEFAULT_USD_UZS', 12600),

    // Mahalliy narxni yaxlitlash (eng yaqin N so'mga). 0 — yaxlitlamaslik.
    'local_rounding' => (int) env('BILLING_LOCAL_ROUNDING', 100),

    // CBU (Markaziy bank) kurs API endpointi.
    'cbu_url' => env('BILLING_CBU_URL', 'https://cbu.uz/uz/arkhiv-kursov-valyut/json/USD/'),
];
