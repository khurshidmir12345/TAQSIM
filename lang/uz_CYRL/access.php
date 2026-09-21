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
        'ending' => "⏳ Taqseem: :days кун қолди\n\nМуддат тугагач Статистика, Буюртмалар ва Ходимлар ёпилади. Қолган бўлимлар бепул ишлайверади.\n\nБарча имкониятлардан тўлиқ фойдаланиш учун премиум олинг 👉 :contact",
        'ending_today' => "⏳ Taqseem: бугун охирги кун\n\nЭртадан Статистика, Буюртмалар ва Ходимлар ёпилади. Қолган бўлимлар бепул ишлайверади.\n\nБарча имкониятлардан тўлиқ фойдаланиш учун премиум олинг 👉 :contact",
        'ended' => "🔒 Статистика, Буюртмалар ва Ходимлар ёпилди\n\nҚолган бўлимлар аввалгидек бепул.\n\nБарча имкониятлардан тўлиқ фойдаланиш учун премиум олинг 👉 :contact",
    ],

    // Premium narxlari — ogohlantirishlar oxiriga qo'shiladi va promo xabarda.
    'pricing' => "⭐ <b>Premium нархлари</b>

📅 Ойлик: <s>39 000 сўм</s> → <b>17 000 сўм/ой</b>
🎁 56% чегирма

🔥 Йиллик: <s>468 000 сўм</s> → <b>150 000 сўм/йил</b>
🎁 68% чегирма — <b>318 000 сўм тежайсиз</b>, ойига атиги ~12 500 сўм",

    // Bir martalik marketing xabari (`access:promo`).
    'promo' => "⭐ <b>Taqseem Premium — махсус чегирма!</b>

Статистика, Буюртмалар ва Ходимлар бўлимларидан тўлиқ фойдаланинг — ҳозирча махсус чегирмали нархда.

:pricing

Premium олиш ва мурожаат учун админ ботга ёзинг 👉 :contact",
];
