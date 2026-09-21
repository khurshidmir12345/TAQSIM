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
        'ending' => "⏳ Taqseem: :days gün kaldı\n\nSonrasında İstatistik, Siparişler ve Çalışanlar kapanır. Gerisi ücretsiz çalışır.\n\nTüm özellikleri kullanmak için premium alın 👉 :contact",
        'ending_today' => "⏳ Taqseem: bugün son gün\n\nYarından İstatistik, Siparişler ve Çalışanlar kapanır. Gerisi ücretsiz çalışır.\n\nTüm özellikleri kullanmak için premium alın 👉 :contact",
        'ended' => "🔒 İstatistik, Siparişler ve Çalışanlar kapandı\n\nGerisi eskisi gibi ücretsiz.\n\nTüm özellikleri kullanmak için premium alın 👉 :contact",
    ],

    // Premium narxlari — ogohlantirishlar oxiriga qo'shiladi va promo xabarda.
    'pricing' => "⭐ <b>Premium fiyatları</b>

📅 Aylık: <s>39 000 som</s> → <b>17 000 som/ay</b>
🎁 %56 indirim

🔥 Yıllık: <s>468 000 som</s> → <b>150 000 som/yıl</b>
🎁 %68 indirim — <b>318 000 som tasarruf</b>, ayda yalnızca ~12 500 som",

    // Bir martalik marketing xabari (`access:promo`).
    'promo' => "⭐ <b>Taqseem Premium — özel indirim!</b>

İstatistik, Siparişler ve Çalışanlar bölümlerini sınırsız kullanın — şimdilik özel fiyatla.

:pricing

Premium almak ve sorularınız için admin bota yazın 👉 :contact",
];
