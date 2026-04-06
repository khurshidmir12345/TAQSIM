<?php

return [
    'success' => 'Сәтті',
    'created' => 'Жасалды',
    'updated' => 'Жаңартылды.',
    'deleted' => 'Жойылды',
    'ping' => 'Taqsim API жұмыс істейді',

    'errors' => [
        'generic' => 'Қате',
        'unauthenticated' => 'Авторизациядан өтпегенсіз.',
        'validation_failed' => 'Деректер дұрыс емес.',
        'not_found' => 'Деректер табылмады.',
        'not_found_http' => 'Бет табылмады.',
        'forbidden' => 'Рұқсат жоқ.',
        'forbidden_shop' => 'Бұл бизнеске кіруге рұқсат жоқ.',
        'forbidden_shop_bakery' => 'Бұл наубайханға кіруге рұқсат жоқ.',
        'forbidden_owner_only' => 'Тек иесі ғана осы әрекетті орындай алады.',
        'rate_limit' => 'Тым көп сұрау. Біраз күтіңіз.',
        'server_error' => 'Сервер қатесі.',
        'invalid_expense_category' => 'Шығын түрі дұрыс емес немесе сізге тиесілі емес.',
        'expense_category_duplicate' => 'Бұл атаумен категория бар немесе жүйелік категориямен сәйкес келеді.',
        'return_production_mismatch' => 'Таңдалған партия өнім түріне немесе күнге сәйкес емес.',
    ],

    'auth' => [
        'send_code_phone_exists' => 'Бұл нөмір тіркелген. Код жіберілді.',
        'send_code_new' => 'Растау коды жіберілді.',
        'register_phone_taken' => 'Бұл телефон тіркелген. Жүйеге кіріңіз.',
        'invalid_code' => 'Код қате немесе мерзімі өткен.',
        'register_success' => 'Тіркелу аяқталды.',
        'login_invalid' => 'Телефон немесе құпия сөз қате.',
        'login_success' => 'Жүйеге кірдіңіз.',
        'profile_updated' => 'Профиль жаңартылды.',
        'avatar_updated' => 'Аватар жаңартылды.',
        'password_changed' => 'Құпия сөз өзгертілді.',
        'account_deleted' => 'Аккаунт жойылды.',
        'logout_success' => 'Жүйеден шықтыңыз.',
    ],

    'shop' => [
        'created' => 'Бизнес сәтті жасалды.',
        'deleted' => 'Бизнес жойылды.',
    ],

    'recipe' => [
        'duplicate_bread_category' => 'Бұл өнім түрі үшін рецепт бұрыннан бар.',
    ],
];
