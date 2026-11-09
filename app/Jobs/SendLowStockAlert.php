<?php

namespace App\Jobs;

use App\Enums\NotificationCategory;
use App\Enums\ShopPermission;
use App\Enums\ShopUserType;
use App\Models\Ingredient;
use App\Models\Shop;
use App\Models\UserShop;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * «Xom ashyo kam qoldi» — do'kon egasi va omborga ruxsati bor xodimlarga.
 */
class SendLowStockAlert implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    /** @param  list<string>  $ingredientIds */
    public function __construct(
        public string $shopId,
        public array $ingredientIds,
    ) {}

    public function handle(NotificationService $notifications): void
    {
        $shop = Shop::query()->find($this->shopId);
        if ($shop === null || $this->ingredientIds === []) {
            return;
        }

        $names = Ingredient::query()
            ->whereIn('id', $this->ingredientIds)
            ->orderBy('name')
            ->pluck('name')
            ->all();
        if ($names === []) {
            return;
        }

        $items = implode(', ', array_slice($names, 0, 3))
            . (count($names) > 3 ? ' +' . (count($names) - 3) : '');

        $pivots = UserShop::query()
            ->where('shop_id', $shop->id)
            ->with('user')
            ->get();

        foreach ($pivots as $pivot) {
            $user = $pivot->user;
            $isOwner = $pivot->user_type === ShopUserType::Owner
                || $pivot->user_type === ShopUserType::Owner->value;
            $canStock = in_array(ShopPermission::ManageRecipes->value, $pivot->permissions ?? [], true);
            if ($user === null || $user->blocked_at !== null || (! $isOwner && ! $canStock)) {
                continue;
            }

            try {
                $notifications->notify(
                    $user,
                    NotificationCategory::LowStock,
                    'notification.low_stock.title',
                    'notification.low_stock.body',
                    ['shop' => (string) $shop->name, 'items' => $items],
                    ['type' => 'low_stock', 'shop_id' => (string) $shop->id],
                );
            } catch (\Throwable $e) {
                Log::warning('Kam qoldi xabari yuborilmadi', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
