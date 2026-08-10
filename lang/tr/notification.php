<?php

/**
 * Zamanlanmış bildirim metinleri.
 *
 * `daily_greeting.bodies` haftanın gününe göre seçilir (0–6), bu yüzden
 * listede tam olarak 7 seçenek bulunmalıdır.
 */
return [

    'daily_greeting' => [
        'title' => 'Günaydın!',
        'bodies' => [
            'Yeni bir gün başlıyor — işleriniz bereketli olsun.',
            'Günaydın! Bugün müşteriniz bol, satışınız bol olsun.',
            'Yeni gün, yeni fırsat. Bol şans!',
            'Bugün için planladığınız her şey gerçekleşsin!',
            'Günaydın! Verimli bir gün geçirin.',
            'Ekmeğiniz sıcak, satışınız bol olsun. İyi günler!',
            'Günaydın! Bugün de işleriniz yolunda gitsin.',
        ],
    ],

    'order_reminder' => [
        'title' => 'Bugünkü siparişler',
        'body' => ':shop — bugün :count sipariş teslim edilmeli.',
    ],

];
