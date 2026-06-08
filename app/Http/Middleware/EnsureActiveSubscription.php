<?php

namespace App\Http\Middleware;

use App\Enums\ShopUserType;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feature route'larni obuna holatiga qarab himoyalaydi:
 *  - trialing / active → to'liq ruxsat
 *  - grace            → faqat o'qish (GET/HEAD), yozish bloklanadi
 *  - expired / cancelled / yo'q → to'liq blok (402)
 *
 * Obuna/wallet/auth route'lariga qo'llanmaydi (ular ochiq qoladi).
 */
class EnsureActiveSubscription
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Faqat xodim (seller) bo'lgan foydalanuvchi shaxsiy obunaga bog'liq emas —
        // uning kirishi biznes egasi obunasiga bog'liq (shop.perm middleware tekshiradi).
        if ($this->isPureEmployee($user)) {
            return $next($request);
        }

        $subscription = $this->subscriptions->current($user)
            ?? $this->subscriptions->ensureTrial($user); // eski userlarni trial bilan backfill

        // Trial tarif sozlanmagan bo'lsa — billing o'chiq, bloklamaymiz.
        if (! $subscription) {
            return $next($request);
        }

        $status = $subscription->effectiveStatus();

        if ($status->hasFullAccess()) {
            return $next($request);
        }

        if ($status->isReadOnly()) {
            if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                return $next($request);
            }

            return $this->deny('subscription_grace_readonly', $subscription);
        }

        return $this->deny('subscription_required', $subscription);
    }

    private function isPureEmployee($user): bool
    {
        return $user->userShops()->where('user_type', ShopUserType::Seller->value)->exists()
            && $user->ownedShops()->doesntExist();
    }

    private function deny(string $code, $subscription = null): Response
    {
        return response()->json([
            'success' => false,
            'message' => __('api.errors.subscription_required'),
            'code' => $code,
            'data' => [
                'status' => $subscription?->effectiveStatus()->value ?? 'expired',
                'grace_days_left' => $subscription?->graceDaysLeft() ?? 0,
            ],
        ], 402);
    }
}
