<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cheklov umuman yoqilganmi
    |--------------------------------------------------------------------------
    |
    | `false` bo'lsa hech kim hech qanday cheklovga uchramaydi — muddati
    | tugagan bo'lsa ham barcha bo'limlar ochiq turadi. Bu deploysiz
    | o'chirish tugmasi: cheklov noto'g'ri ishlab qolsa yoki ommaviy
    | shikoyat bo'lsa, `.env` da bir qatorni o'zgartirib to'xtatiladi.
    |
    | `config/app_update.php` dagi `enabled` bilan bir xil mantiq.
    |
    */

    'enabled' => (bool) env('ACCESS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Sinov muddati (kun)
    |--------------------------------------------------------------------------
    |
    | Yangi foydalanuvchi ro'yxatdan o'tganda `users.access_until` shu
    | kunga qo'yiladi. Muddat ichida barcha bo'limlar ochiq.
    |
    */

    'trial_days' => (int) env('ACCESS_TRIAL_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Muddat tugagach yopiladigan bo'limlar
    |--------------------------------------------------------------------------
    |
    | Bu ro'yxatdagilar faqat muddati o'tmagan foydalanuvchilarga ochiq.
    | Ro'yxatda yo'q hamma narsa — bosh sahifa, mahsulot/xomashyo/retsept,
    | ishlab chiqarish, qaytarish, kassa — doim bepul.
    |
    | Kalitlar ilovaga `ShopResource.features` ichida uzatiladi va u shu
    | ro'yxatga qarab bo'limni ochadi yoki neytral xabar ko'rsatadi.
    |
    */

    'paid_features' => [
        'reports',      // Statistika: grafik, haqiqiy tannarx, hisobotlar
        'orders',       // Buyurtmalar + Mijozlar
        'employees',    // Xodim qo'shish va ruxsatlarini boshqarish
        'multi_shop',   // Ikkinchi va undan keyingi biznes
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram ogohlantirishlari
    |--------------------------------------------------------------------------
    |
    | Muddat tugashiga shuncha kun qolganda bot orqali xabar yuboriladi.
    | `0` — tugash kunining o'zi ("bugun tugaydi"), manfiy qiymat esa
    | tugagandan keyingi kun ("tugadi, bo'limlar yopildi"). Aynan o'sha kuni
    | foydalanuvchi yopilganini sezadi — xabar shunda eng foydali.
    |
    | Har bir nuqta bir marta yuboriladi (`access_notices` jadvali orqali).
    |
    | Xabar matni faqat serverda, `lang` katalogidagi `access` faylida —
    | mobil ilova binary'siga tushmaydi.
    |
    */

    'notice_days' => [7, 3, 1, 0, -1],

    /*
    |--------------------------------------------------------------------------
    | Aloqa manzili
    |--------------------------------------------------------------------------
    |
    | Telegram xabarining oxirida ko'rsatiladi: foydalanuvchi kim bilan
    | bog'lanishini bilishi kerak. Faqat bot xabarida ishlatiladi — ilovaga
    | uzatilmaydi.
    |
    */

    'contact' => env('ACCESS_CONTACT', '@taqseem_admin_bot'),

];
