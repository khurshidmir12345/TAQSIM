<?php

namespace App\Services;

use App\Enums\IngredientMovementType;
use App\Jobs\SendLowStockAlert;
use App\Models\Ingredient;
use App\Models\IngredientMovement;
use App\Models\Production;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Xom ashyo ombori.
 *
 * Pul qoidasi: xom ashyo puli kassadan **xarid paytida** chiqadi. Mahsulot
 * chiqimida esa ombor kamayadi, kassaga xom ashyo chiqimi yozilmaydi (pul
 * allaqachon ketgan). Asosiy sahifadagi foyda esa tannarxni avvalgidek
 * ayiradi — u pul harakati emas, sarflangan xom ashyo qiymati.
 *
 * Birlik narxi o'rtacha tortilgan: har kirimda eski qoldiq qiymati bilan
 * yangi kirim birga hisoblanadi.
 */
class InventoryService
{
    /** O'rtacha sarf shuncha kun bo'yicha hisoblanadi. */
    public const USAGE_WINDOW_DAYS = 14;

    /** Avtomatik chegara: shundan kam kunga yetsa — «kam qoldi». */
    public const LOW_DAYS = 2;

    public function __construct(
        private readonly CashMirrorService $mirror,
    ) {}

    /** Xarid: ombor +, o'rtacha narx yangilanadi, kassaga chiqim. */
    public function purchase(
        Shop $shop,
        Ingredient $ingredient,
        float $quantity,
        float $totalAmount,
        string $date,
        ?string $userId,
        ?string $note = null,
    ): IngredientMovement {
        return DB::transaction(function () use ($shop, $ingredient, $quantity, $totalAmount, $date, $userId, $note) {
            $this->start($shop);

            $ingredient = Ingredient::query()->lockForUpdate()->findOrFail($ingredient->id);
            $unitPrice = $quantity > 0 ? $totalAmount / $quantity : 0.0;

            $oldStock = (float) $ingredient->stock_quantity;
            $oldPrice = (float) $ingredient->price_per_unit;
            $newPrice = $oldStock > 0
                ? ($oldStock * $oldPrice + $totalAmount) / ($oldStock + $quantity)
                : $unitPrice;

            $movement = IngredientMovement::create([
                'shop_id' => $shop->id,
                'ingredient_id' => $ingredient->id,
                'type' => IngredientMovementType::Purchase,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_amount' => round($totalAmount, 2),
                'note' => $note,
                'date' => $date,
                'created_by' => $userId,
            ]);

            $ingredient->price_per_unit = round($newPrice, 2);
            $ingredient->save();
            $this->recalc([$ingredient->id]);
            $this->checkLow($shop, [$ingredient->id]);

            $this->mirror->syncIngredientPurchase($movement->setRelation('ingredient', $ingredient));

            return $movement;
        });
    }

    /** Sanash: haqiqiy qoldiq kiritiladi, farq harakat bo'lib yoziladi. */
    public function adjust(
        Shop $shop,
        Ingredient $ingredient,
        float $actualQuantity,
        ?string $userId,
        ?string $note = null,
    ): ?IngredientMovement {
        return DB::transaction(function () use ($shop, $ingredient, $actualQuantity, $userId, $note) {
            $this->start($shop);

            $ingredient = Ingredient::query()->lockForUpdate()->findOrFail($ingredient->id);
            $diff = round($actualQuantity - (float) $ingredient->stock_quantity, 3);
            if (abs($diff) < 0.0005) {
                return null;
            }

            $price = (float) $ingredient->price_per_unit;
            $movement = IngredientMovement::create([
                'shop_id' => $shop->id,
                'ingredient_id' => $ingredient->id,
                'type' => IngredientMovementType::Adjustment,
                'quantity' => $diff,
                'unit_price' => $price,
                'total_amount' => round(abs($diff) * $price, 2),
                'note' => $note,
                'date' => now()->toDateString(),
                'created_by' => $userId,
            ]);
            $this->recalc([$ingredient->id]);
            $this->checkLow($shop, [$ingredient->id]);

            return $movement;
        });
    }

    /** Kirim yoki tuzatishni bekor qilish (sarf faqat chiqim orqali). */
    public function deleteMovement(IngredientMovement $movement): void
    {
        if ($movement->type === IngredientMovementType::Consumption) {
            throw ValidationException::withMessages([
                'movement' => __('api.inventory.consumption_locked'),
            ]);
        }

        DB::transaction(function () use ($movement) {
            $this->mirror->forgetIngredientPurchase($movement);
            $ingredientId = $movement->ingredient_id;
            $movement->delete();
            $this->recalc([$ingredientId]);
            if ($movement->shop) {
                $this->checkLow($movement->shop, [$ingredientId]);
            }
        });
    }

    /**
     * Chiqim yaratildi yoki o'zgardi — sarf harakatlari qaytadan yoziladi.
     * Ombor yuritilmayotgan yoki retseptsiz chiqimda hech narsa yozilmaydi.
     */
    public function syncProduction(Production $production): void
    {
        $shop = $production->shop;
        $old = IngredientMovement::query()
            ->where('production_id', $production->id)
            ->pluck('ingredient_id')
            ->all();

        IngredientMovement::query()->where('production_id', $production->id)->delete();

        if (! $shop instanceof Shop || ! $shop->tracksInventoryFor($production)) {
            $this->recalc($old);

            return;
        }

        $production->loadMissing('recipe.recipeIngredients.ingredient');
        $batch = (float) $production->batch_count;
        $touched = $old;

        foreach ($production->recipe?->recipeIngredients ?? [] as $ri) {
            $qty = round((float) $ri->quantity * $batch, 3);
            if ($qty <= 0) {
                continue;
            }
            $price = (float) ($ri->ingredient?->price_per_unit ?? 0);
            IngredientMovement::create([
                'shop_id' => $shop->id,
                'ingredient_id' => $ri->ingredient_id,
                'type' => IngredientMovementType::Consumption,
                'quantity' => -$qty,
                'unit_price' => $price,
                'total_amount' => round($qty * $price, 2),
                'production_id' => $production->id,
                'date' => $production->date,
                'created_by' => $production->created_by,
            ]);
            $touched[] = $ri->ingredient_id;
        }

        $this->recalc($touched);
        $this->checkLow($shop, $touched);
    }

    /**
     * Har xom ashyo uchun o'rtacha kunlik sarf, necha kunga yetishi va
     * «kam qoldi» belgisi.
     *
     * @return array<string, array{daily_usage: float, days_left: float|null, is_low: bool}>
     */
    public function stockStats(Shop $shop, ?array $ingredientIds = null): array
    {
        $since = now()->subDays(self::USAGE_WINDOW_DAYS - 1)->toDateString();
        $usage = IngredientMovement::query()
            ->where('shop_id', $shop->id)
            ->where('type', IngredientMovementType::Consumption->value)
            ->whereDate('date', '>=', $since)
            ->when($ingredientIds !== null, fn ($q) => $q->whereIn('ingredient_id', $ingredientIds))
            ->groupBy('ingredient_id')
            ->selectRaw('ingredient_id, -SUM(quantity) as used')
            ->pluck('used', 'ingredient_id');

        $ingredients = Ingredient::query()
            ->where('shop_id', $shop->id)
            ->when($ingredientIds !== null, fn ($q) => $q->whereIn('id', $ingredientIds))
            ->get(['id', 'stock_quantity', 'min_stock']);

        $out = [];
        foreach ($ingredients as $i) {
            $daily = (float) ($usage[$i->id] ?? 0) / self::USAGE_WINDOW_DAYS;
            $stock = (float) $i->stock_quantity;
            $daysLeft = $daily > 0 ? max(0.0, $stock) / $daily : null;
            $isLow = $i->min_stock !== null
                ? $stock <= (float) $i->min_stock
                : ($daysLeft !== null && $daysLeft < self::LOW_DAYS);

            $out[$i->id] = [
                'daily_usage' => round($daily, 3),
                'days_left' => $daysLeft !== null ? round($daysLeft, 1) : null,
                'is_low' => $isLow,
            ];
        }

        return $out;
    }

    public function setMinStock(Ingredient $ingredient, ?float $minStock): void
    {
        $ingredient->update(['min_stock' => $minStock]);
        if ($ingredient->shop) {
            $this->checkLow($ingredient->shop, [$ingredient->id]);
        }
    }

    /**
     * Yangi «kam qoldi» holatiga tushganlar uchun bitta xabar; qoldiq
     * tiklanganlarda belgi tozalanadi — keyingi safar yana xabar beriladi.
     *
     * @param  list<string>  $ingredientIds
     */
    private function checkLow(Shop $shop, array $ingredientIds): void
    {
        $ids = array_values(array_unique(array_filter($ingredientIds)));
        if ($ids === [] || $shop->inventory_started_at === null) {
            return;
        }

        $stats = $this->stockStats($shop, $ids);
        $fresh = [];
        $recovered = [];
        $notified = Ingredient::query()->whereIn('id', $ids)->pluck('low_notified_at', 'id');

        foreach ($stats as $id => $st) {
            if ($st['is_low'] && $notified[$id] === null) {
                $fresh[] = $id;
            } elseif (! $st['is_low'] && $notified[$id] !== null) {
                $recovered[] = $id;
            }
        }

        if ($recovered !== []) {
            Ingredient::query()->whereIn('id', $recovered)->update(['low_notified_at' => null]);
        }
        if ($fresh !== []) {
            Ingredient::query()->whereIn('id', $fresh)->update(['low_notified_at' => now()]);
            SendLowStockAlert::dispatch($shop->id, $fresh)->afterCommit();
        }
    }

    public function forgetProduction(Production $production): void
    {
        $ids = IngredientMovement::query()
            ->where('production_id', $production->id)
            ->pluck('ingredient_id')
            ->all();
        IngredientMovement::query()->where('production_id', $production->id)->delete();
        $this->recalc($ids);
    }

    /** Ombor birinchi kirim yoki sanash bilan boshlanadi. */
    private function start(Shop $shop): void
    {
        if ($shop->inventory_started_at === null) {
            $shop->forceFill(['inventory_started_at' => now()])->save();
        }
    }

    /** @param  list<string>  $ingredientIds */
    private function recalc(array $ingredientIds): void
    {
        foreach (array_unique(array_filter($ingredientIds)) as $id) {
            $sum = (float) IngredientMovement::query()->where('ingredient_id', $id)->sum('quantity');
            Ingredient::withTrashed()->whereKey($id)->update(['stock_quantity' => round($sum, 3)]);
        }
    }
}
