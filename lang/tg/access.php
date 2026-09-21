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
        'ending' => "⏳ Taqseem: :days рӯз мондааст\n\nБаъд аз он Омор, Фармоишҳо ва Кормандон баста мешаванд. Боқимонда ройгон кор мекунад.\n\nБарои истифодаи ҳамаи имкониятҳо премиум гиред 👉 :contact",
        'ending_today' => "⏳ Taqseem: имрӯз рӯзи охирин\n\nАз фардо Омор, Фармоишҳо ва Кормандон баста мешаванд. Боқимонда ройгон кор мекунад.\n\nБарои истифодаи ҳамаи имкониятҳо премиум гиред 👉 :contact",
        'ended' => "🔒 Омор, Фармоишҳо ва Кормандон баста шуданд\n\nБоқимонда мисли пештара ройгон.\n\nБарои истифодаи ҳамаи имкониятҳо премиум гиред 👉 :contact",
    ],

    // Premium narxlari — ogohlantirishlar oxiriga qo'shiladi va promo xabarda.
    'pricing' => "⭐ <b>Нархҳои Premium</b>

📅 Моҳона: <s>39 000 сум</s> → <b>17 000 сум/моҳ</b>
🎁 56% тахфиф

🔥 Солона: <s>468 000 сум</s> → <b>150 000 сум/сол</b>
🎁 68% тахфиф — <b>318 000 сум сарфа мекунед</b>, ҳамагӣ ~12 500 сум дар моҳ",

    // Bir martalik marketing xabari (`access:promo`).
    'promo' => "⭐ <b>Taqseem Premium — тахфифи махсус!</b>

Аз бахшҳои Омор, Фармоишҳо ва Кормандон пурра истифода баред — ҳоло бо нархи махсус.

:pricing

Барои гирифтани Premium ва савол ба админ-бот нависед 👉 :contact",
];
