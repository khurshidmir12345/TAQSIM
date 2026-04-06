<?php

return [
    'success' => 'Ийгиликтүү',
    'created' => 'Түзүлдү',
    'updated' => 'Жаңыланды.',
    'deleted' => 'Өчүрүлдү',
    'ping' => 'Taqsim API иштеп жатат',

    'errors' => [
        'generic' => 'Ката',
        'unauthenticated' => 'Авторизациядан өтө элексиңиз.',
        'validation_failed' => 'Маалыматтар туура эмес.',
        'not_found' => 'Маалымат табылган жок.',
        'not_found_http' => 'Баракча табылган жок.',
        'forbidden' => 'Уруксат жок.',
        'forbidden_shop' => 'Бул бизнеске кирүүгө уруксат жок.',
        'forbidden_shop_bakery' => 'Бул нон жайына кирүүгө уруксат жок.',
        'forbidden_owner_only' => 'Бул аракетти ээ гана аткара алат.',
        'rate_limit' => 'Өтө көп суроо. Күтүңүз.',
        'server_error' => 'Сервер катасы.',
        'invalid_expense_category' => 'Чыгым түрү туура эмес же сизге таандык эмес.',
        'expense_category_duplicate' => 'Мындай ат менен категория бар же системалык категория менен дал келет.',
        'return_production_mismatch' => 'Тандалган партия өнүм түрүнө же күнгө дал келбейт.',
    ],

    'auth' => [
        'send_code_phone_exists' => 'Бул номер мурун катталган. Код жөнөтүлдү.',
        'send_code_new' => 'Ырастоо коду жөнөтүлдү.',
        'register_phone_taken' => 'Бул телефон мурун катталган. Системага кириңиз.',
        'invalid_code' => 'Код туура эмес же мөөнөтү өттү.',
        'register_success' => 'Катталуу аяктады.',
        'login_invalid' => 'Телефон же сырсөз туура эмес.',
        'login_success' => 'Системага кирдиңиз.',
        'profile_updated' => 'Профиль жаңыланды.',
        'avatar_updated' => 'Аватар жаңыланды.',
        'password_changed' => 'Сырсөз өзгөртүлдү.',
        'account_deleted' => 'Аккаунт өчүрүлдү.',
        'logout_success' => 'Системадан чыктыңыз.',
    ],

    'shop' => [
        'created' => 'Бизнес ийгиликтүү түзүлдү.',
        'deleted' => 'Бизнес өчүрүлдү.',
    ],

    'recipe' => [
        'duplicate_bread_category' => 'Бул өнүм түрү үчүн рецепт мурунтан эле бар.',
    ],
];
