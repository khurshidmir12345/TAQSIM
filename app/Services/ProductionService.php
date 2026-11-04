<?php

namespace App\Services;

use App\Models\BreadCategory;
use App\Models\BreadReturn;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductionService
{
    /**
     * Calculate total ingredient cost for given batch count.
     * cost = base_cost_per_batch × batch_count
     */
    public function calculateIngredientCost(Recipe $recipe, float $batchCount): float
    {
        $recipe->loadMissing('recipeIngredients.ingredient');

        $baseCost = $recipe->recipeIngredients->sum(function ($ri) {
            return (float) $ri->quantity * (float) $ri->ingredient->price_per_unit;
        });

        return round($baseCost * $batchCount, 2);
    }

    /**
     * Calculate bread output for given batch count.
     * output = output_quantity_per_batch × batch_count
     */
    public function calculateBreadOutput(Recipe $recipe, float $batchCount): int
    {
        return (int) round($recipe->output_quantity * $batchCount);
    }

    /**
     * Calculate total flour used (kg) for given batch count.
     * flour = flour_per_batch × batch_count
     */
    public function calculateFlourUsed(Recipe $recipe, float $batchCount): float
    {
        $recipe->loadMissing('recipeIngredients.ingredient');

        $flourPerBatch = $recipe->recipeIngredients
            ->filter(fn ($ri) => $ri->ingredient->is_flour)
            ->sum(fn ($ri) => (float) $ri->quantity);

        return round($flourPerBatch * $batchCount, 2);
    }

    // ─── Bir partiyadan bir nechta mahsulot ────────────────────────────────

    /**
     * Retseptning odatiy taqsimoti × partiya soni.
     *
     * @return array<string, int> bread_category_id => miqdor
     */
    public function defaultItems(Recipe $recipe, float $batchCount): array
    {
        $recipe->loadMissing('outputs');

        $items = [];
        foreach ($recipe->outputs as $o) {
            $items[$o->bread_category_id] = (int) round($o->quantity * $batchCount);
        }

        if ($items === [] && $recipe->bread_category_id) {
            $items[$recipe->bread_category_id] = $this->calculateBreadOutput($recipe, $batchCount);
        }

        return $items;
    }

    /**
     * Partiya xarajati, xom ashyosi va partiya sonini mahsulotlarga bo'ladi.
     *
     * Ulush — sotuv qiymati bo'yicha (miqdor × narx): ko'proq pul keltiradigan
     * mahsulot xarajatning ham kattaroq qismini ko'taradi. Narxlar nol bo'lsa —
     * miqdor bo'yicha. Yaxlitlash «eng katta qoldiq» usulida: qatorlar
     * yig'indisi partiya jamisiga tiyinigacha teng bo'ladi.
     *
     * @param  array<string, int>  $items  bread_category_id => miqdor (> 0)
     * @return list<array{bread_category_id: string, bread_produced: int, batch_count: float, ingredient_cost: float, flour_used_kg: float}>
     */
    public function allocate(Recipe $recipe, float $batchCount, array $items): array
    {
        $catIds = array_keys($items);
        $prices = BreadCategory::withTrashed()
            ->whereIn('id', $catIds)
            ->pluck('selling_price', 'id');

        $weights = [];
        foreach ($items as $catId => $qty) {
            $weights[$catId] = $qty * (float) ($prices[$catId] ?? 0);
        }
        if (array_sum($weights) <= 0) {
            $weights = array_map(fn ($q) => (float) $q, $items);
        }

        $batchUnits = $this->distribute((int) round($batchCount * 10000), $weights);
        $costCents = $this->distribute(
            (int) round($this->calculateIngredientCost($recipe, $batchCount) * 100),
            $weights
        );
        $flourUnits = $this->distribute(
            (int) round($this->calculateFlourUsed($recipe, $batchCount) * 100),
            $weights
        );

        $rows = [];
        foreach ($items as $catId => $qty) {
            $rows[] = [
                'bread_category_id' => (string) $catId,
                'bread_produced' => (int) $qty,
                'batch_count' => $batchUnits[$catId] / 10000,
                'ingredient_cost' => $costCents[$catId] / 100,
                'flour_used_kg' => $flourUnits[$catId] / 100,
            ];
        }

        return $rows;
    }

    /**
     * Partiyani (bir yoki bir nechta mahsulot qatori) yaratadi yoki yangilaydi.
     *
     * Mavjud qatorlar mahsulot bo'yicha joyida yangilanadi (id saqlanadi —
     * vozvratlar va kassa yozuvlari bog'liq), yangi mahsulot qo'shiladi,
     * olib tashlangani o'chiriladi. Vozvrati bor mahsulotni olib tashlab
     * bo'lmaydi.
     *
     * @param  array<string, int>  $items  bread_category_id => miqdor
     * @param  Collection<int, Production>|null  $existing
     * @return Collection<int, Production>
     */
    public function saveBatch(
        Shop $shop,
        Recipe $recipe,
        float $batchCount,
        array $items,
        string $date,
        string $userId,
        ?Collection $existing = null,
    ): Collection {
        $items = array_filter(
            array_map('intval', $items),
            fn (int $q) => $q > 0
        );

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => __('api.production.items_empty'),
            ]);
        }

        $existing ??= collect();
        $byCategory = $existing->keyBy('bread_category_id');

        $removed = $existing->filter(fn (Production $p) => ! isset($items[$p->bread_category_id]));
        if ($removed->isNotEmpty()
            && BreadReturn::query()->whereIn('production_id', $removed->pluck('id'))->exists()) {
            throw ValidationException::withMessages([
                'items' => __('api.production.item_has_returns'),
            ]);
        }

        $rows = $this->allocate($recipe, $batchCount, $items);
        $batchId = count($rows) > 1
            ? ($existing->first()?->batch_id ?? (string) Str::uuid())
            : null;
        $createdAt = $existing->first()?->created_at ?? now();
        $date = $existing->first()?->date?->toDateString() ?? $date;

        return DB::transaction(function () use ($shop, $recipe, $rows, $removed, $byCategory, $batchId, $createdAt, $date, $userId) {
            foreach ($removed as $p) {
                $p->delete();
            }

            $saved = collect();
            foreach ($rows as $row) {
                $p = $byCategory->get($row['bread_category_id']);
                if ($p instanceof Production) {
                    $p->fill($row + ['batch_id' => $batchId])->save();
                } else {
                    $p = new Production($row + [
                        'shop_id' => $shop->id,
                        'recipe_id' => $recipe->id,
                        'batch_id' => $batchId,
                        'date' => $date,
                        'created_by' => $userId,
                    ]);
                    $p->created_at = $createdAt;
                    $p->save();
                }
                $saved->push($p);
            }

            return $saved;
        });
    }

    /**
     * Eski ilova versiyasi partiyadagi bitta qatorning sonini o'zgartirsa —
     * faqat o'sha qator mutanosib o'zgaradi (mahsulot miqdori, xarajat, un).
     */
    public function rescaleRow(Production $p, Recipe $recipe, float $newBatchCount): Production
    {
        $old = (float) $p->batch_count;
        $ratio = $old > 0 ? $newBatchCount / $old : 1.0;

        $p->update([
            'batch_count' => round($newBatchCount, 4),
            'bread_produced' => (int) round($p->bread_produced * $ratio),
            'ingredient_cost' => $this->calculateIngredientCost($recipe, $newBatchCount),
            'flour_used_kg' => $this->calculateFlourUsed($recipe, $newBatchCount),
        ]);

        return $p;
    }

    /**
     * Butun sonni og'irliklarga mutanosib bo'ladi; yig'indi aynan $total.
     *
     * @param  array<string, float>  $weights
     * @return array<string, int>
     */
    private function distribute(int $total, array $weights): array
    {
        $sum = array_sum($weights);
        $out = [];
        $remainders = [];
        $assigned = 0;

        foreach ($weights as $key => $w) {
            $exact = $sum > 0 ? $total * $w / $sum : $total / max(1, count($weights));
            $floor = (int) floor($exact);
            $out[$key] = $floor;
            $remainders[$key] = $exact - $floor;
            $assigned += $floor;
        }

        arsort($remainders);
        $left = $total - $assigned;
        foreach (array_keys($remainders) as $key) {
            if ($left <= 0) {
                break;
            }
            $out[$key]++;
            $left--;
        }

        return $out;
    }

    /**
     * Kun bo‘yicha partiyalar uchun vozvratni tur bo‘yicha chiqim ulushiga bo‘lib,
     * netto tushum va foydani hisoblaydi (mobil kartalar va batafsil uchun).
     *
     * @return array<string, array{returns_amount: float, returns_quantity_allocated: int, gross_revenue: float, net_revenue: float, net_profit: float}>
     */
    public function financialsForProductions(Shop $shop, Collection $productions, ?string $date): array
    {
        $out = [];
        foreach ($productions as $p) {
            $out[$p->id] = [
                'returns_amount' => 0.0,
                'returns_quantity_allocated' => 0,
                'gross_revenue' => 0.0,
                'net_revenue' => 0.0,
                'net_profit' => 0.0,
            ];
        }

        if ($productions->isEmpty()) {
            return $out;
        }

        foreach ($productions as $p) {
            $gross = $this->grossRevenueForProduction($p);
            $out[$p->id]['gross_revenue'] = round($gross, 2);
            $out[$p->id]['net_revenue'] = round($gross, 2);
            $out[$p->id]['net_profit'] = round($gross - (float) $p->ingredient_cost, 2);
        }

        if ($date !== null && $date !== '') {
            return $this->applyReturnsAllocationForDate(
                $shop,
                $productions,
                \Carbon\Carbon::parse($date)->toDateString(),
                $out
            );
        }

        foreach ($productions->groupBy(fn ($p) => $p->date->toDateString()) as $dateStr => $group) {
            $out = $this->applyReturnsAllocationForDate($shop, $group, $dateStr, $out);
        }

        return $out;
    }

    /**
     * @param  array<string, array{returns_amount: float, returns_quantity_allocated: int, gross_revenue: float, net_revenue: float, net_profit: float}>  $out
     * @return array<string, array{returns_amount: float, returns_quantity_allocated: int, gross_revenue: float, net_revenue: float, net_profit: float}>
     */
    private function applyReturnsAllocationForDate(
        Shop $shop,
        Collection $productions,
        string $dateStr,
        array $out
    ): array {
        $returns = $shop->breadReturns()->whereDate('date', $dateStr)->get();

        $directReturns = $returns->whereNotNull('production_id');
        $nullReturns = $returns->whereNull('production_id');

        $directAmtByProduction = $directReturns->groupBy('production_id')
            ->map(fn (Collection $g) => (float) $g->sum('total_amount'));
        $directQtyByProduction = $directReturns->groupBy('production_id')
            ->map(fn (Collection $g) => (int) $g->sum('quantity'));

        $nullAmtByCategory = $nullReturns->groupBy('bread_category_id')
            ->map(fn (Collection $g) => (float) $g->sum('total_amount'));
        $nullQtyByCategory = $nullReturns->groupBy('bread_category_id')
            ->map(fn (Collection $g) => (int) $g->sum('quantity'));

        $breadByCategory = $productions->groupBy('bread_category_id')
            ->map(fn (Collection $g) => (int) $g->sum('bread_produced'));

        foreach ($productions as $p) {
            $pid = $p->id;
            $catId = $p->bread_category_id;
            $bread = (int) $p->bread_produced;
            $catBread = max(0, (int) $breadByCategory->get($catId, 0));

            $directAmt = (float) $directAmtByProduction->get($pid, 0.0);
            $directQty = (int) $directQtyByProduction->get($pid, 0);

            $nullAmt = (float) $nullAmtByCategory->get($catId, 0.0);
            $nullQty = (int) $nullQtyByCategory->get($catId, 0);

            $allocatedNullAmt = $catBread > 0 ? $nullAmt * ($bread / $catBread) : 0.0;
            $allocatedNullQty = $catBread > 0 ? (int) round($nullQty * ($bread / $catBread)) : 0;

            $allocatedAmt = $directAmt + $allocatedNullAmt;
            $allocatedQty = $directQty + $allocatedNullQty;

            $gross = $this->grossRevenueForProduction($p);
            $netRev = $gross - $allocatedAmt;
            $netProfit = $netRev - (float) $p->ingredient_cost;

            $out[$p->id] = [
                'returns_amount' => round($allocatedAmt, 2),
                'returns_quantity_allocated' => $allocatedQty,
                'gross_revenue' => round($gross, 2),
                'net_revenue' => round($netRev, 2),
                'net_profit' => round($netProfit, 2),
            ];
        }

        return $out;
    }

    private function grossRevenueForProduction(Production $p): float
    {
        $p->loadMissing('breadCategory');
        $price = (float) ($p->breadCategory?->selling_price ?? 0);

        return (int) $p->bread_produced * $price;
    }
}
