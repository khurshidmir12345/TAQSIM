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
        'from'  => env('DEVSMS_FROM', '4546'),
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
        'jwks_url'   => env('APPLE_JWKS_URL', 'https://appleid.apple.com/auth/keys'),
        'issuer'     => env('APPLE_ISSUER', 'https://appleid.apple.com'),
    ],

];
