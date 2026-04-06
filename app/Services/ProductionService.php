<?php

namespace App\Services;

use App\Models\Production;
use App\Models\Recipe;
use App\Models\Shop;
use Illuminate\Support\Collection;

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
