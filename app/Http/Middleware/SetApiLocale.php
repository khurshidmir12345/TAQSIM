<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accept-Language sarlavhasi bo‘yicha Laravel locale ni o‘rnatadi (mobil ilova tillari bilan mos).
 */
class SetApiLocale
{
    /** Mobil ilova bilan bir xil kodlar (mobile/lib/core/l10n/app_locale.dart). */
    protected const array SUPPORTED = [
        'uz',
        'uz_CYRL',
        'ru',
        'kk',
        'ky',
        'tr',
        'tg',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolveLocale($request));

        return $next($request);
    }

    protected function resolveLocale(Request $request): string
    {
        $header = $request->header('Accept-Language', '');
        if ($header === '') {
            return config('app.locale');
        }

        $first = trim(explode(',', $header)[0]);
        $first = explode(';', $first)[0];
        $first = str_replace('-', '_', trim($first));

        $lower = strtolower($first);

        foreach (self::SUPPORTED as $locale) {
            if (strtolower($locale) === $lower) {
                return $locale;
            }
        }

        if (str_starts_with($lower, 'uz')) {
            return 'uz';
        }

        return config('app.locale');
    }
}
