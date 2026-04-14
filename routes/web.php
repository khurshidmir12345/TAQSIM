<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/app-redirect', function () {
    $deepLink = 'taqseem://auth/callback';

    return response(<<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>TAQSEEM</title>
        <style>
            body { font-family: -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #F7F9FB; color: #0B3C5D; text-align: center; }
            .card { padding: 40px 24px; max-width: 360px; }
            h2 { margin-bottom: 8px; }
            p { color: #6B7280; margin-bottom: 24px; }
            a { display: inline-block; padding: 14px 32px; background: #00A896; color: #fff; border-radius: 12px; text-decoration: none; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="card">
            <h2>TAQSEEM</h2>
            <p>Ilovaga qaytish uchun pastdagi tugmani bosing</p>
            <a href="{$deepLink}">Ilovani ochish</a>
        </div>
        <script>window.location.href = "{$deepLink}";</script>
    </body>
    </html>
    HTML, 200, ['Content-Type' => 'text/html']);
});
