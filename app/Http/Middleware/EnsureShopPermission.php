<?php

namespace App\Http\Middleware;

use App\Enums\ShopUserType;
use App\Models\Shop;
use App\Models\UserShop;
use App\Services\EmployeeService;
use App\Services\SubscriptionService;
use App\Support\ApiMeta;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Do'kon ichidagi modul ruxsatini tekshiradi.
 *
 * Foydalanish: shop.perm:manage_products            (faqat yozish amallarini gate qiladi)
 *              shop.perm:view_reports,read           (o'qishni ham gate qiladi)
 *              shop.perm:owner                        (faqat owner)
 *
 * Owner doim ruxsatga ega. Pulli o'rin to'lovi tugagan xodim (past_due) bloklanadi.
 */
class EnsureShopPermission
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly EmployeeService $employees,
    ) {}

    public function handle(Request $request, Closure $next, string $permission, string $mode = 'write'): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->deny(__('api.errors.unauthenticated'), 'unauthenticated', 401);
        }

        $routeShop = $request->route('shop');
        $shopId = $routeShop instanceof Shop ? $routeShop->id : $routeShop;

        $pivot = UserShop::query()
            ->where('shop_id', $shopId)
            ->where('user_id', $user->id)
            ->first();

        if (! $pivot) {
            return $this->deny(__('api.errors.forbidden_shop'), 'forbidden_shop', 403);
        }

        if ($pivot->user_type === ShopUserType::Owner) {
            return $next($request);
        }

        // ── Xodim (seller) ──
        // Seller faqat obunasi (seller_subs) AKTIV bo'lsa ishlay oladi (trial yo'q).
        if (! $this->employees->isSellerActive($shopId, $user->id)) {
            return $this->deny(__('api.employees.seat_suspended'), 'seat_suspended', 403);
        }

        if ($permission === 'owner') {
            return $this->deny(__('api.errors.forbidden_owner_only'), 'forbidden_owner_only', 403);
        }

        $isRead = in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);

        // Xodim kirishi biznes egasining obuna holatiga bog'liq.
        if ($ownerDenied = $this->checkOwnerSubscription($shopId, $isRead)) {
            return $ownerDenied;
        }

        if ($isRead && $mode !== 'read') {
            return $next($request);
        }

        if (! $pivot->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => __('api.errors.forbidden_permission'),
                'code' => 'forbidden_permission',
                'data' => ['permission' => $permission],
                'meta' => ApiMeta::withUserType(),
            ], 403);
        }

        return $next($request);
    }

    /** Biznes egasining obunasi tugagan bo'lsa xodimga ham mos javob qaytaradi. */
    private function checkOwnerSubscription(string $shopId, bool $isRead): ?Response
    {
        $ownerPivot = UserShop::query()
            ->where('shop_id', $shopId)
            ->where('user_type', ShopUserType::Owner->value)
            ->with('user')
            ->first();

        $owner = $ownerPivot?->user;

        if (! $owner) {
            return null;
        }

        $subscription = $this->subscriptions->current($owner);

        if (! $subscription) {
            return null; // billing o'chiq
        }

        $status = $subscription->effectiveStatus();

        if ($status->hasFullAccess()) {
            return null;
        }

        if ($status->isReadOnly() && $isRead) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => __('api.employees.owner_subscription_inactive'),
            'code' => 'owner_subscription_inactive',
            'meta' => ApiMeta::withUserType(),
        ], 403);
    }

    private function deny(string $message, string $code, int $status): Response
    {
        $payload = [
            'success' => false,
            'message' => $message,
            'code' => $code,
        ];

        $meta = ApiMeta::withUserType();

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }
}
