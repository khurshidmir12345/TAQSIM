<?php

/**
 * Yangi foydalanuvchining birinchi biznesiga avtomatik qo'shiladigan
 * namunaviy mahsulot, xom ashyo va retseptlar — biznes turi (`business_types.key`)
 * bo'yicha. Ko'pchilik sozlashni bilmaydi: tayyor namunani ko'rib, o'ziga
 * moslab o'zgartiradi yoki o'chiradi.
 *
 * Nomlar `lang/<til>/default_catalog.php` da. Narxlar so'mda — do'kon
 * valyutasi UZS bo'lmasa 0 qo'yiladi (foydalanuvchi o'zi kiritadi).
 *
 * Miqdorlar haqiqiy nonvoyxonalar retseptlaridan o'rtacha olingan
 * (1 qop = 50 kg un).
 */
return [
    'bakery' => [
        'ingredients' => [
            // kalit => [birlik kodi, narx (so'm), un'mi]
            'flour' => ['unit' => 'kg', 'price' => 5500, 'is_flour' => true],
            'salt' => ['unit' => 'kg', 'price' => 2000],
            'yeast' => ['unit' => 'kg', 'price' => 45000],
            'oil' => ['unit' => 'kg', 'price' => 22000],
            'sesame' => ['unit' => 'kg', 'price' => 28000],
        ],
        'products' => [
            'big_bread' => [
                'price' => 4000,
                'batch_unit' => 'qop',
                'output' => 170,
                'recipe' => ['flour' => 50, 'salt' => 1, 'yeast' => 0.3, 'oil' => 2, 'sesame' => 0.5],
            ],
            'small_bread' => [
                'price' => 2500,
                'batch_unit' => 'qop',
                'output' => 250,
                'recipe' => ['flour' => 50, 'salt' => 1, 'yeast' => 0.3, 'oil' => 1, 'sesame' => 0.4],
            ],
        ],
    ],
];
