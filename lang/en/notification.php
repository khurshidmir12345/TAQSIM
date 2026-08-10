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
            'A new day begins — may your work go well today.',
            'Good morning! Wishing you plenty of customers and strong sales.',
            'A new day, a new opportunity. Make it count!',
            'May everything you planned for today work out.',
            'Good morning! Have a productive day.',
            'Fresh bread, good sales — have a great day!',
            'Good morning! May today run smoothly for you.',
        ],
    ],

    'order_reminder' => [
        'title' => 'Today’s orders',
        'body' => ':shop — :count order(s) to deliver today.',
    ],

];
