<?php

/*
 * Muddat haqidagi ogohlantirishlar — FAQAT Telegram bot orqali yuboriladi.
 *
 * Bu matnlar ataylab serverda turadi: ilova ichida narx, to'lov yoki tarif
 * haqida bir og'iz so'z bo'lmasligi kerak (do'kon qoidalari). Ilovadan
 * tashqarida — botda, SMS'da, qo'ng'iroqda — bularni aytish mumkin.
 */

return [
    'notice' => [
        'ending' => "Hello!\n\nYour full access to Taqseem ends in :days day(s).\n\nAfter that, Statistics, Orders and Employees will close. Home, products and recipes, production, returns and the cash register keep working for free.\n\nTo keep using every feature, get the premium plan — contact the administrator: :contact",
        'ended' => "Hello!\n\nYour full access to Taqseem has ended. Statistics, Orders and Employees are now closed.\n\nHome, products and recipes, production, returns and the cash register keep working for free as before.\n\nTo reopen the closed sections, get the premium plan — contact the administrator: :contact",
    ],

];
