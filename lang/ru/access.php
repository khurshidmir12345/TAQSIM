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
        'ending' => "Здравствуйте!\n\nСрок полного доступа в Taqseem заканчивается через :days дн.\n\nПосле этого разделы «Статистика», «Заказы» и «Сотрудники» будут закрыты. Главная, товары и рецепты, производство, возвраты и касса продолжат работать бесплатно.\n\nЧтобы продолжить пользоваться всеми возможностями, оформите премиум-тариф — свяжитесь с администратором: :contact",
        'ended' => "Здравствуйте!\n\nСрок полного доступа в Taqseem закончился. Разделы «Статистика», «Заказы» и «Сотрудники» закрыты.\n\nГлавная, товары и рецепты, производство, возвраты и касса работают бесплатно как прежде.\n\nЧтобы снова открыть закрытые разделы, оформите премиум-тариф — свяжитесь с администратором: :contact",
    ],

];
