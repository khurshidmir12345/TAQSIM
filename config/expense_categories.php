<?php

/**
 * Tizim (built-in) kassa kategoriyalari — kalitlar saqlanadi (`expenses.category`,
 * `cash_transactions.category`). Foydalanuvchi yaratganlari `expense_categories`
 * jadvalida (UUID) va `type` ustuni bilan ajratiladi.
 */
return [
    'built_in' => [
        'otyin' => ['icon' => 'fire'],
        'gaz' => ['icon' => 'gas_station'],
        'elektr' => ['icon' => 'bolt'],
        'ijara' => ['icon' => 'apartment'],
        'transport' => ['icon' => 'directions_bus'],
        'ish_haqi' => ['icon' => 'payments'],
        'kommunal' => ['icon' => 'water'],
        'maosh' => ['icon' => 'badge'],
        'boshqa' => ['icon' => 'more_horiz'],
    ],

    // Kirim turlari — nomlari lang/<til>/cash.php dagi income_categories bo'limida.
    'built_in_income' => [
        'sotuv' => ['icon' => 'sell'],
        'qarz_qaytdi' => ['icon' => 'handshake'],
        'qoshimcha_mablag' => ['icon' => 'savings'],
        'boshqa' => ['icon' => 'more_horiz'],
    ],
];
