<?php

return [
    'success' => 'Успешно',
    'created' => 'Создано',
    'updated' => 'Обновлено.',
    'deleted' => 'Удалено',
    'ping' => 'API Taqsim работает',

    'errors' => [
        'generic' => 'Ошибка',
        'unauthenticated' => 'Требуется авторизация.',
        'validation_failed' => 'Данные указаны неверно.',
        'not_found' => 'Данные не найдены.',
        'not_found_http' => 'Страница не найдена.',
        'forbidden' => 'Доступ запрещён.',
        'forbidden_shop' => 'Нет доступа к этому бизнесу.',
        'forbidden_shop_bakery' => 'Нет доступа к этой пекарне.',
        'forbidden_owner_only' => 'Это действие может выполнить только владелец.',
        'rate_limit' => 'Слишком много запросов. Подождите немного.',
        'server_error' => 'Ошибка сервера.',
        'invalid_expense_category' => 'Неверная категория расхода или она вам не принадлежит.',
        'expense_category_duplicate' => 'Категория с таким названием уже есть или совпадает с системной.',
        'return_production_mismatch' => 'Выбранная партия не соответствует типу продукции или дате.',
    ],

    'auth' => [
        'send_code_phone_exists' => 'Этот номер уже зарегистрирован. Код отправлен.',
        'send_code_new' => 'Код подтверждения отправлен.',
        'register_phone_taken' => 'Этот телефон уже зарегистрирован. Войдите в систему.',
        'invalid_code' => 'Код неверный или срок действия истёк.',
        'register_success' => 'Регистрация завершена.',
        'login_invalid' => 'Неверный телефон или пароль.',
        'login_success' => 'Вы вошли в систему.',
        'profile_updated' => 'Профиль обновлён.',
        'avatar_updated' => 'Аватар обновлён.',
        'password_changed' => 'Пароль изменён.',
        'account_deleted' => 'Аккаунт удалён.',
        'logout_success' => 'Вы вышли из системы.',
    ],

    'shop' => [
        'created' => 'Бизнес успешно создан.',
        'deleted' => 'Бизнес удалён.',
    ],

    'recipe' => [
        'duplicate_bread_category' => 'Рецепт для этой категории продукции уже существует.',
    ],
];
