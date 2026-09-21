<?php

/*
 * Muddat haqidagi ogohlantirishlar — FAQAT Telegram bot orqali yuboriladi.
 *
 * Bu matnlar ataylab serverda turadi: ilova ichida narx, to'lov yoki tarif
 * haqida bir og'iz so'z bo'lmasligi kerak (do'kon qoidalari). Ilovadan
 * tashqarida — botda, SMS'da, qo'ng'iroqda — bularni aytish mumkin.
 *
 * Qisqa yozilgan: uzun xabarni hech kim oxirigacha o'qimaydi. Har biri
 * bitta sarlavha, bitta izoh va bitta chaqiruvdan iborat.
 */

return [
    'notice' => [
        'ending' => "⏳ Taqseem: :days days left\n\nAfter that Statistics, Orders and Employees close. Everything else stays free.\n\nGet premium to use every feature 👉 :contact",
        'ending_today' => "⏳ Taqseem: last day\n\nFrom tomorrow Statistics, Orders and Employees close. Everything else stays free.\n\nGet premium to use every feature 👉 :contact",
        'ended' => "🔒 Statistics, Orders and Employees are closed\n\nEverything else works free as before.\n\nGet premium to use every feature 👉 :contact",
    ],

    // Premium narxlari — ogohlantirishlar oxiriga qo'shiladi va promo xabarda.
    'pricing' => "⭐ <b>Premium pricing</b>

📅 Monthly: <s>39 000 UZS</s> → <b>17 000 UZS/month</b>
🎁 56% off

🔥 Yearly: <s>468 000 UZS</s> → <b>150 000 UZS/year</b>
🎁 68% off — <b>save 318 000 UZS</b>, just ~12 500 UZS a month",

    // Bir martalik marketing xabari (`access:promo`).
    'promo' => "⭐ <b>Taqseem Premium — special discount!</b>

Use Statistics, Orders and Employees without limits — at a special price for now.

:pricing

To get Premium or ask a question, message the admin bot 👉 :contact",
];
