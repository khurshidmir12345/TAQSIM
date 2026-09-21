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
        'ending' => "⏳ Taqseem: :days kun qoldi\n\nMuddat tugagach Statistika, Buyurtmalar va Xodimlar yopiladi. Qolgan bo'limlar bepul ishlayveradi.\n\nBarcha imkoniyatlardan to'liq foydalanish uchun premium oling 👉 :contact",
        'ending_today' => "⏳ Taqseem: bugun oxirgi kun\n\nErtadan Statistika, Buyurtmalar va Xodimlar yopiladi. Qolgan bo'limlar bepul ishlayveradi.\n\nBarcha imkoniyatlardan to'liq foydalanish uchun premium oling 👉 :contact",
        'ended' => "🔒 Statistika, Buyurtmalar va Xodimlar yopildi\n\nQolgan bo'limlar avvalgidek bepul.\n\nBarcha imkoniyatlardan to'liq foydalanish uchun premium oling 👉 :contact",
    ],

    // Premium narxlari — ogohlantirishlar oxiriga qo'shiladi va promo xabarda.
    'pricing' => "⭐ <b>Premium narxlari</b>

📅 Oylik: <s>39 000 so'm</s> → <b>17 000 so'm/oy</b>
🎁 56% chegirma

🔥 Yillik: <s>468 000 so'm</s> → <b>150 000 so'm/yil</b>
🎁 68% chegirma — <b>318 000 so'm tejaysiz</b>, oyiga atigi ~12 500 so'm",

    // Bir martalik marketing xabari (`access:promo`).
    'promo' => "⭐ <b>Taqseem Premium — maxsus chegirma!</b>

Statistika, Buyurtmalar va Xodimlar bo'limlaridan to'liq foydalaning — hozircha maxsus chegirmali narxda.

:pricing

Premium olish va murojaat uchun admin botga yozing 👉 :contact",
];
