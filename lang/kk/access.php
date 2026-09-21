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
        'ending' => "⏳ Taqseem: :days күн қалды\n\nСодан кейін Статистика, Тапсырыстар және Қызметкерлер жабылады. Қалғаны тегін жұмыс істейді.\n\nБарлық мүмкіндіктерді пайдалану үшін премиум алыңыз 👉 :contact",
        'ending_today' => "⏳ Taqseem: бүгін соңғы күн\n\nЕртеңнен Статистика, Тапсырыстар және Қызметкерлер жабылады. Қалғаны тегін жұмыс істейді.\n\nБарлық мүмкіндіктерді пайдалану үшін премиум алыңыз 👉 :contact",
        'ended' => "🔒 Статистика, Тапсырыстар және Қызметкерлер жабылды\n\nҚалғаны бұрынғыдай тегін.\n\nБарлық мүмкіндіктерді пайдалану үшін премиум алыңыз 👉 :contact",
    ],

    // Premium narxlari — ogohlantirishlar oxiriga qo'shiladi va promo xabarda.
    'pricing' => "⭐ <b>Premium бағалары</b>

📅 Айлық: <s>39 000 сум</s> → <b>17 000 сум/ай</b>
🎁 56% жеңілдік

🔥 Жылдық: <s>468 000 сум</s> → <b>150 000 сум/жыл</b>
🎁 68% жеңілдік — <b>318 000 сум үнемдейсіз</b>, айына небәрі ~12 500 сум",

    // Bir martalik marketing xabari (`access:promo`).
    'promo' => "⭐ <b>Taqseem Premium — арнайы жеңілдік!</b>

Статистика, Тапсырыстар және Қызметкерлер бөлімдерін толық пайдаланыңыз — әзірге арнайы бағамен.

:pricing

Premium алу және сұрақ үшін админ-ботқа жазыңыз 👉 :contact",
];
