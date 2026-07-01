<?php

return [
    'success' => 'Muvaffaqiyatli',
    'created' => 'Yaratildi',
    'updated' => 'Yangilandi.',
    'deleted' => 'O‘chirildi',
    'ping' => 'Taqsim API ishlayapti',

    'errors' => [
        'account_blocked' => 'Hisobingiz bloklangan. Iltimos, administrator bilan bog‘laning.',
        'generic' => 'Xatolik',
        'unauthenticated' => 'Avtorizatsiyadan o‘tilmagan.',
        'validation_failed' => 'Ma’lumotlar noto‘g‘ri.',
        'not_found' => 'Ma’lumot topilmadi.',
        'not_found_http' => 'Sahifa topilmadi.',
        'forbidden' => 'Ruxsat yo‘q.',
        'forbidden_shop' => 'Bu biznesga kirishga ruxsat yo‘q.',
        'forbidden_shop_bakery' => 'Bu nonvoyxonaga kirishga ruxsat yo‘q.',
        'forbidden_owner_only' => 'Faqat egasi bu amalni bajara oladi.',
        'forbidden_permission' => 'Bu bo‘lim uchun ruxsatingiz yo‘q.',
        'rate_limit' => 'Juda ko‘p so‘rov. Biroz kuting.',
        'server_error' => 'Serverda xatolik yuz berdi.',
        'invalid_expense_category' => 'Xarajat turi noto‘g‘ri yoki sizga tegishli emas.',
        'expense_category_duplicate' => 'Bu nom bilan kategoriya allaqachon mavjud yoki tizim kategoriyasi bilan mos keladi.',
        'return_production_mismatch' => 'Tanlangan partiya ushbu mahsulot turi yoki sanaga mos kelmaydi.',
    ],

    'auth' => [
        'send_code_phone_exists' => 'Bu raqam allaqachon ro‘yxatdan o‘tgan. Kod yuborildi.',
        'send_code_new' => 'Tasdiqlash kodi yuborildi.',
        'register_phone_taken' => 'Bu telefon raqami allaqachon ro‘yxatdan o‘tgan. Iltimos, tizimga kiring.',
        'invalid_code' => 'Tasdiqlash kodi noto‘g‘ri yoki muddati o‘tgan.',
        'register_success' => 'Ro‘yxatdan o‘tildi.',
        'login_invalid' => 'Telefon raqam yoki parol noto‘g‘ri.',
        'login_success' => 'Tizimga kirdingiz.',
        'profile_updated' => 'Profil yangilandi.',
        'avatar_updated' => 'Avatar yangilandi.',
        'avatar_removed' => 'Avatar olib tashlandi.',
        'password_changed' => 'Parol o‘zgartirildi.',
        'account_deleted' => 'Hisob o‘chirildi.',
        'logout_success' => 'Tizimdan chiqdingiz.',
        'device_revoked' => 'Qurilma chiqarildi.',
        'apple_invalid_token' => 'Apple identifikatsiya tokeni yaroqsiz yoki muddati o‘tgan.',
        'apple_login_success' => 'Apple ID orqali tizimga kirdingiz.',
        'google_invalid_token' => 'Google identifikatsiya tokeni yaroqsiz yoki muddati o‘tgan.',
        'google_login_success' => 'Google orqali tizimga kirdingiz.',
    ],

    'shop' => [
        'created' => 'Biznes muvaffaqiyatli yaratildi.',
        'deleted' => 'Biznes o‘chirildi.',
    ],

    'recipe' => [
        'duplicate_bread_category' => 'Bu mahsulot turi uchun retsept allaqachon mavjud.',
    ],

    'employees' => [
        'code_sent' => 'Tasdiqlash kodi xodim raqamiga yuborildi.',
        'created' => 'Xodim qo‘shildi.',
        'phone_taken' => 'Bu raqam allaqachon ro‘yxatdan o‘tgan. Boshqa raqam kiriting.',
        'invite_expired' => 'Taklif muddati tugadi. Qaytadan urinib ko‘ring.',
    ],
];
