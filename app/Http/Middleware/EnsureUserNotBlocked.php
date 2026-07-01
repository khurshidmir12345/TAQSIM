<?php

namespace App\Http\Middleware;

use App\Support\ApiMeta;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloklangan foydalanuvchini himoyalangan route'lardan chetlatadi.
 *
 * Admin foydalanuvchini bloklaganda uning tokenlari o'chiriladi, lekin
 * bu middleware qo'shimcha himoya sifatida har bir so'rovda tekshiradi.
 * Blok holatida joriy token darhol bekor qilinadi va 403 qaytariladi.
 */
class EnsureUserNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isBlocked()) {
            $token = $user->currentAccessToken();
            if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
                $token->delete();
            }

            return response()->json([
                'success' => false,
                'message' => __('api.errors.account_blocked'),
                'code' => 'account_blocked',
                'meta' => ApiMeta::withUserType(),
            ], 403);
        }

        return $next($request);
    }
}
