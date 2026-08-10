<?php

/**
 * Scheduled notification texts.
 *
 * `daily_greeting.bodies` is picked by day of week (0–6), so the list must
 * contain exactly 7 variants.
 */
return [

    'daily_greeting' => [
        'title' => 'Good morning!',
        'bodies' => [
            'The TAQSEEM team wishes you a great mood and a blessed working day!',
            'The TAQSEEM team wishes you plenty of customers and smooth sales today!',
            'The TAQSEEM team hopes everything you planned for today works out!',
            'The TAQSEEM team wishes you productive work and a calm day!',
            'The TAQSEEM team wishes you fresh bread and happy customers!',
            'The TAQSEEM team wishes you health, luck and prosperity!',
            'The TAQSEEM team wishes you a day that runs smoothly!',
        ],
    ],

    'order_reminder' => [
        'title' => 'Today’s orders',
        'body' => ':shop — :count order(s) to deliver today.',
    ],

];
