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
            'TAQSEEM ekibi size bugünkü iş gününüz için güzel bir moral ve bereket diler!',
            'TAQSEEM ekibi size bugün bol müşteri ve kolay satışlar diler!',
            'TAQSEEM ekibi bugün için planladığınız her şeyin gerçekleşmesini diler!',
            'TAQSEEM ekibi size verimli bir çalışma ve huzurlu bir gün diler!',
            'TAQSEEM ekibi ekmeğinizin sıcak, satışınızın bol olmasını diler!',
            'TAQSEEM ekibi size sağlık, şans ve bereket diler!',
            'TAQSEEM ekibi bugün de işlerinizin yolunda gitmesini diler!',
        ],
    ],

    'order_reminder' => [
        'title' => 'Bugünkü siparişler',
        'body' => ':shop — bugün :count sipariş teslim edilmeli.',
    ],

];
