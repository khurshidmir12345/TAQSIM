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
        'ending' => "⏳ Taqseem: осталось :days дн.\n\nПотом «Статистика», «Заказы» и «Сотрудники» закроются. Остальное работает бесплатно.\n\nЧтобы пользоваться всеми возможностями, оформите премиум 👉 :contact",
        'ending_today' => "⏳ Taqseem: сегодня последний день\n\nС завтра «Статистика», «Заказы» и «Сотрудники» закроются. Остальное работает бесплатно.\n\nЧтобы пользоваться всеми возможностями, оформите премиум 👉 :contact",
        'ended' => "🔒 «Статистика», «Заказы» и «Сотрудники» закрыты\n\nОстальное работает бесплатно, как раньше.\n\nЧтобы пользоваться всеми возможностями, оформите премиум 👉 :contact",
    ],

    // Premium narxlari — ogohlantirishlar oxiriga qo'shiladi va promo xabarda.
    'pricing' => "⭐ <b>Цены Premium</b>

📅 Месяц: <s>39 000 сум</s> → <b>17 000 сум/мес</b>
🎁 скидка 56%

🔥 Год: <s>468 000 сум</s> → <b>150 000 сум/год</b>
🎁 скидка 68% — <b>экономия 318 000 сум</b>, всего ~12 500 сум в месяц",

    // Bir martalik marketing xabari (`access:promo`).
    'promo' => "⭐ <b>Taqseem Premium — специальная скидка!</b>

Пользуйтесь разделами «Статистика», «Заказы» и «Сотрудники» без ограничений — пока по специальной цене.

:pricing

Чтобы оформить Premium или задать вопрос, напишите в админ-бот 👉 :contact",
];
