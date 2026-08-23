<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Services\AccessService;
use App\Support\ApiMeta;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bo'lim shu do'konda ochiqmi — muddat bo'yicha tekshiradi.
 *
 * Foydalanish: shop.feature:reports
 *
 * `shop.perm` roldan (owner/seller) kelib chiqadi, bu esa hisob muddatidan.
 * Ikkalasi mustaqil: egasi ham, ruxsatli xodim ham muddat tugagach bir xil
 * javob oladi — hisob egaga bog'langan.
 *
 * Ro'yxatda yo'q bo'lim tekshirilmaydi: bosh sahifa, ishlab chiqarish,
 * qaytarish, kassa, retsept — doim ochiq.
 */
class EnsureShopFeature
{
    public function __construct(
        private readonly AccessService $access,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $routeShop = $request->route('shop');

        $shop = $routeShop instanceof Shop
            ? $routeShop
            : Shop::query()->find($routeShop);

        // Do'kon topilmasa qaror chiqarmaymiz — `shop.perm` yoki
        // kontroller o'z xatosini qaytarsin.
        if ($shop === null) {
            return $next($request);
        }

        if ($this->access->shopHasFeature($shop, $feature)) {
            return $next($request);
        }

        return self::denied();
    }

    /**
     * Yopiq bo'lim javobi.
     *
     * Matn neytral: narx, to'lov yoki obuna haqida bir so'z ham yo'q —
     * ilova buni shundayligicha ko'rsatadi.
     */
    public static function denied(): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => __('api.errors.feature_unavailable'),
            'code' => 'feature_unavailable',
        ];

        $meta = ApiMeta::withUserType();

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, 403);
    }
}
