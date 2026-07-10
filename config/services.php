<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'devsms' => [
        'token' => env('DEVSMS_TOKEN'),
        'from' => env('DEVSMS_FROM', '4546'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'apple' => [
        /*
        |--------------------------------------------------------------------
        | Sign in with Apple
        |--------------------------------------------------------------------
        | iOS app bundle identifier(lar) — vergul bilan ajratib bir nechta
        | qiymat berish mumkin (development va production uchun).
        */
        'client_ids' => array_filter(array_map('trim', explode(',', env('APPLE_CLIENT_IDS', 'uz.taqseem.app')))),
        'jwks_url' => env('APPLE_JWKS_URL', 'https://appleid.apple.com/auth/keys'),
        'issuer' => env('APPLE_ISSUER', 'https://appleid.apple.com'),
    ],

    'telegram' => [
        /*
        |--------------------------------------------------------------------
        | Telegram Auth — Web return URL
        |--------------------------------------------------------------------
        | Web klient uchun Telegram login tugagandan so'ng foydalanuvchi
        | qaytariladigan manzil. Mobil klient uchun bu qiymat ishlatilmaydi —
        | u hamon `/auth/app-redirect` deep-link sahifasidan foydalanadi.
        */
        'web_app_url' => env('WEB_APP_URL', 'https://web.taqseem.uz'),
    ],

    'google' => [
        /*
        |--------------------------------------------------------------------
        | Google Sign-In
        |--------------------------------------------------------------------
        | Ruxsat etilgan OAuth client ID(lar) — vergul bilan ajratiladi.
        | ID token `aud` shulardan biriga teng bo'lishi shart:
        |  - Web (server) client ID — Android `serverClientId` shu bo'lganda
        |    token audience web client bo'ladi.
        |  - iOS client ID — iOS native sign-in uchun.
        |  - Android client ID — zaxira (ba'zi konfiguratsiyalarda).
        */
        'client_ids' => array_filter(array_map('trim', explode(',', (string) env('GOOGLE_CLIENT_IDS', '')))),
        'jwks_url' => env('GOOGLE_JWKS_URL', 'https://www.googleapis.com/oauth2/v3/certs'),
        // Google ID token `iss` ikki variantdan biri bo'ladi.
        'issuers' => ['https://accounts.google.com', 'accounts.google.com'],
    ],

];
