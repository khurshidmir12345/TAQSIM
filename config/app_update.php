<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Yangilanish modalkasi yoqilganmi
    |--------------------------------------------------------------------------
    |
    | `false` bo'lsa ilova umuman modalka ko'rsatmaydi — versiya qanday
    | bo'lishidan qat'i nazar. Do'kondagi versiya kechikib tasdiqlansa yoki
    | modalka noto'g'ri chiqib qolsa, deploysiz o'chirish uchun kerak.
    |
    */

    'enabled' => (bool) env('APP_UPDATE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Platformalar bo'yicha oxirgi versiya
    |--------------------------------------------------------------------------
    |
    | `version` — do'konda mavjud eng so'nggi versiya. Ilovaning o'z versiyasi
    | shundan kichik bo'lsa "yangilanish bor" deyiladi. Format: `1.2.8` yoki
    | build raqami bilan `1.2.8+40` (build raqami faqat versiyalar teng
    | bo'lganda solishtiriladi).
    |
    | `url` — "Yangilash" tugmasi ochadigan do'kon manzili. Bo'sh bo'lsa
    | modalka faqat xabar sifatida ko'rinadi, tugmasiz.
    |
    | iOS va Android alohida: App Store tekshiruvi kechikkanda iOS
    | foydalanuvchilarini hali chiqmagan versiyaga chaqirmaslik uchun.
    |
    */

    'android' => [
        'version' => env('APP_UPDATE_ANDROID_VERSION', ''),
        'url' => env(
            'APP_UPDATE_ANDROID_URL',
            'https://play.google.com/store/apps/details?id=uz.taqseem.mobile'
        ),
    ],

    'ios' => [
        'version' => env('APP_UPDATE_IOS_VERSION', ''),
        'url' => env('APP_UPDATE_IOS_URL', ''),
    ],

];
