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
            'TAQSEEM jamoasi sizga bugungi ish kuningiz uchun yaxshi kayfiyat va baraka tilaydi!',
            'TAQSEEM jamoasi sizga bugun ko‘p mijoz va ravon savdo tilaydi!',
            'TAQSEEM jamoasi bugungi rejalaringiz amalga oshishini tilaydi. Ishingizga baraka!',
            'TAQSEEM jamoasi sizga serunum mehnat va xotirjam kun tilaydi!',
            'TAQSEEM jamoasi noningiz issiq, savdongiz shirin bo‘lishini tilaydi!',
            'TAQSEEM jamoasi sizga sog‘lik, omad va keng rizq tilaydi!',
            'TAQSEEM jamoasi bugun ham ishingiz o‘ngidan kelishini tilaydi!',
        ],
    ],

    'order_reminder' => [
        'title' => 'Bugungi zakazlar',
        'body' => ':shop — bugun :count ta zakaz yetkazib berilishi kerak.',
    ],

];
