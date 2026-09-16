<?php

/**
 * Kassa: kirim turlari va avtomatik yozuvlarning nomlari.
 *
 * `auto_categories` — mahsulot chiqimi va vozvratdan ko'chirilgan yozuvlar.
 */
return [

    'income_categories' => [
        'sotuv' => 'Sales',
        'qarz_qaytdi' => 'Debt repaid',
        'qoshimcha_mablag' => 'Additional funds',
        'boshqa' => 'Other',
    ],

    'auto_categories' => [
        'outlet_credit' => 'Credit to outlet',
        'outlet_credit_reduced' => 'Outlet credit reduced',
        'outlet_payment' => 'Payment from outlet',
        'production_income' => 'Product revenue',
        'production_cost' => 'Ingredients',
        'return' => 'Returns',
    ],

];
