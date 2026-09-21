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
        'ending' => "⏳ Taqseem: :days күн калды\n\nАндан кийин Статистика, Буйрутмалар жана Кызматкерлер жабылат. Калганы акысыз иштейт.\n\nБардык мүмкүнчүлүктөрдү колдонуу үчүн премиум алыңыз 👉 :contact",
        'ending_today' => "⏳ Taqseem: бүгүн акыркы күн\n\nЭртеңден Статистика, Буйрутмалар жана Кызматкерлер жабылат. Калганы акысыз иштейт.\n\nБардык мүмкүнчүлүктөрдү колдонуу үчүн премиум алыңыз 👉 :contact",
        'ended' => "🔒 Статистика, Буйрутмалар жана Кызматкерлер жабылды\n\nКалганы мурункудай акысыз.\n\nБардык мүмкүнчүлүктөрдү колдонуу үчүн премиум алыңыз 👉 :contact",
    ],

    // Premium narxlari — ogohlantirishlar oxiriga qo'shiladi va promo xabarda.
    'pricing' => "⭐ <b>Premium баалары</b>

📅 Айлык: <s>39 000 сум</s> → <b>17 000 сум/ай</b>
🎁 56% арзандатуу

🔥 Жылдык: <s>468 000 сум</s> → <b>150 000 сум/жыл</b>
🎁 68% арзандатуу — <b>318 000 сум үнөмдөйсүз</b>, айына болгону ~12 500 сум",

    // Bir martalik marketing xabari (`access:promo`).
    'promo' => "⭐ <b>Taqseem Premium — атайын арзандатуу!</b>

Статистика, Заказдар жана Кызматкерлер бөлүмдөрүн толук колдонуңуз — азырынча атайын баада.

:pricing

Premium алуу жана суроо үчүн админ-ботко жазыңыз 👉 :contact",
];
