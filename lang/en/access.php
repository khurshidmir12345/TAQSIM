<?php

/*
 * Muddat haqidagi ogohlantirishlar — FAQAT Telegram bot orqali yuboriladi.
 *
 * Bu matnlar ataylab serverda turadi: ilova ichida narx, to'lov yoki tarif
 * haqida bir og'iz so'z bo'lmasligi kerak (do'kon qoidalari). Ilovadan
 * tashqarida — botda, SMS'da, qo'ng'iroqda — bularni aytish mumkin.
 *
 * Qisqa yozilgan: uzun xabarni hech kim oxirigacha o'qimaydi. Har biri
 * bitta sarlavha, bitta izoh va bitta aniq harakatdan iborat.
 */

return [
    'notice' => [
        'ending' => "⏳ Taqseem: :days days left\n\nAfter that Statistics, Orders and Employees close. Everything else stays free.\n\nKeep them open 👉 :contact",
        'ending_today' => "⏳ Taqseem: last day\n\nFrom tomorrow Statistics, Orders and Employees close. Everything else stays free.\n\nKeep them open 👉 :contact",
        'ended' => "🔒 Statistics, Orders and Employees are closed\n\nEverything else works free as before.\n\nOpen them again 👉 :contact",
    ],

];
