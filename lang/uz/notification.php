<?php

/**
 * Rejalashtirilgan bildirishnoma matnlari.
 *
 * `daily_greeting.bodies` — hafta kuni bo‘yicha tanlanadi (0–6), shuning
 * uchun ro‘yxatda aynan 7 ta variant bo‘lishi shart.
 */
return [

    'daily_greeting' => [
        'title' => 'Xayrli tong!',
        'bodies' => [
            'Yangi kun muborak! Bugungi ishlaringiz barakali bo‘lsin.',
            'Xayrli tong! Bugun mijozlaringiz ko‘p, savdongiz ravon bo‘lsin.',
            'Yangi kun — yangi imkoniyat. Rizqingiz keng bo‘lsin!',
            'Ishingizga baraka! Bugungi rejalaringiz amalga oshsin.',
            'Xayrli tong! Mehnatingiz serunum, kuningiz xayrli bo‘lsin.',
            'Non issiq, savdo shirin bo‘lsin. Kuningiz muborak!',
            'Xayrli tong! Bugun ham ishingiz o‘ngidan kelsin.',
        ],
    ],

    'order_reminder' => [
        'title' => 'Bugungi zakazlar',
        'body' => ':shop — bugun :count ta zakaz yetkazib berilishi kerak.',
    ],

];
