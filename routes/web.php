<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/app-redirect', function (Request $request) {
    $session = (string) $request->query('session', '');

    $deepLink = 'taqseem://auth/callback';
    if ($session !== '') {
        $deepLink .= '?session=' . urlencode($session);
    }

    $deepLinkJs = json_encode($deepLink, JSON_UNESCAPED_SLASHES);
    $deepLinkHtml = htmlspecialchars($deepLink, ENT_QUOTES, 'UTF-8');

    $html = <<<HTML
    <!DOCTYPE html>
    <html lang="uz">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>TAQSEEM</title>
        <style>
            * { box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                display: flex; align-items: center; justify-content: center;
                min-height: 100vh; margin: 0; padding: 24px;
                background: linear-gradient(180deg, #F7F9FB 0%, #E8F0F2 100%);
                color: #0B3C5D; text-align: center;
            }
            .card {
                padding: 40px 28px; max-width: 380px; width: 100%;
                background: #ffffff; border-radius: 20px;
                box-shadow: 0 12px 40px rgba(11, 60, 93, 0.08);
            }
            h2 { margin: 0 0 8px; font-weight: 800; letter-spacing: 0.5px; }
            p { color: #6B7280; margin: 0 0 24px; line-height: 1.5; font-size: 14px; }
            a.btn {
                display: inline-block; padding: 14px 32px;
                background: #00A896; color: #fff; border-radius: 12px;
                text-decoration: none; font-weight: 700; font-size: 15px;
                box-shadow: 0 6px 18px rgba(0, 168, 150, 0.25);
            }
            .mini { margin-top: 16px; font-size: 12px; color: #9CA3AF; }
        </style>
    </head>
    <body>
        <div class="card">
            <h2>TAQSEEM</h2>
            <p>Ilovaga qaytish uchun pastdagi tugmani bosing.</p>
            <a class="btn" href="{$deepLinkHtml}">Ilovani ochish</a>
            <div class="mini">Agar ilova ochilmasa, qurilmangizda TAQSEEM ilovasi o'rnatilganiga ishonch hosil qiling.</div>
        </div>
        <script>
            (function() {
                var link = {$deepLinkJs};
                setTimeout(function() { window.location.href = link; }, 80);
            })();
        </script>
    </body>
    </html>
    HTML;

    return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
});
