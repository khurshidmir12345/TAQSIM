<?php

namespace App\Services;

use App\Enums\ShopUserType;
use App\Models\BreadCategory;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserShop;

/**
 * Tarif limitlarini hisob (account) bo'yicha tekshiradi.
 * Limit null bo'lsa — cheksiz. Trial davrida trial tarif limitlari (cheksiz) amal qiladi.
 */
class PlanLimitService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    /** Limit tekshiruvi uchun amaldagi tarif (to'liq kirish bo'lsa). */
    public function effectivePlan(User $user): ?SubscriptionPlan
    {
        $subscription = $this->subscriptions->current($user);

        if (! $subscription) {
            return null;
        }

        return $subscription->effectiveStatus()->hasFullAccess()
            ? $subscription->plan
            : null;
    }

    /** @return array<string,string> owned shop id'lari */
    private function ownedShopIds(User $user): array
    {
        return $user->ownedShops()->pluck('shops.id')->all();
    }

    public function shopsUsed(User $user): int
    {
        return $user->ownedShops()->count();
    }

    public function productsUsed(User $user): int
    {
        $shopIds = $this->ownedShopIds($user);

        return $shopIds === []
            ? 0
            : BreadCategory::query()->whereIn('shop_id', $shopIds)->count();
    }

    public function employeesUsed(User $user): int
    {
        $shopIds = $this->ownedShopIds($user);

        return $shopIds === []
            ? 0
            : UserShop::query()
                ->whereIn('shop_id', $shopIds)
                ->where('user_type', ShopUserType::Seller->value)
                ->distinct('user_id')
                ->count('user_id');
    }

    public function canAddShop(User $user): bool
    {
        return $this->within($user, 'max_shops', $this->shopsUsed($user));
    }

    public function canAddProduct(User $user): bool
    {
        return $this->within($user, 'max_products', $this->productsUsed($user));
    }

    public function canAddEmployee(User $user): bool
    {
        return $this->within($user, 'max_employees', $this->employeesUsed($user));
    }

    /**
     * Limit + ishlatilgan miqdorni qaytaradi (UI / xato xabari uchun).
     *
     * @return array{limit:int|null, used:int, unlimited:bool, remaining:int|null}
     */
    public function info(User $user, string $resource): array
    {
        $plan = $this->effectivePlan($user);
        $field = "max_{$resource}";
        $limit = $plan?->{$field};

        $used = match ($resource) {
            'shops' => $this->shopsUsed($user),
            'products' => $this->productsUsed($user),
            'employees' => $this->employeesUsed($user),
            default => 0,
        };

        return [
            'limit' => $limit,
            'used' => $used,
            'unlimited' => $limit === null,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
        ];
    }

    private function within(User $user, string $field, int $used): bool
    {
        $plan = $this->effectivePlan($user);

        if (! $plan) {
            // Billing sozlanmagan bo'lsa (trial tarif yo'q) — cheksiz.
            if (! $this->subscriptions->trialPlan()) {
                return true;
            }

            return false; // bloklangan / trial tugagan
        }

        $limit = $plan->{$field};

        return $limit === null || $used < $limit;
    }
}
