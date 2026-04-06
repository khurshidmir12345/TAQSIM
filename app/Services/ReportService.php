<?php

namespace App\Services;

use App\Models\Shop;
use Carbon\Carbon;

class ReportService
{
    /**
     * Bitta kun uchun to'liq hisobot.
     */
    public function daily(Shop $shop, string $date): array
    {
        $date = Carbon::parse($date)->toDateString();

        return $this->buildReport($shop, $date, $date);
    }

    /**
     * Sana oralig'i uchun hisobot.
     */
    public function range(Shop $shop, string $from, string $to): array
    {
        $from = Carbon::parse($from)->toDateString();
        $to = Carbon::parse($to)->toDateString();

        return $this->buildReport($shop, $from, $to);
    }

    private function buildReport(Shop $shop, string $from, string $to): array
    {
        $fromDt = Carbon::parse($from)->startOfDay();
        $toDt = Carbon::parse($to)->endOfDay();

        $productions = $shop->productions()
            ->with('breadCategory')
            ->whereBetween('date', [$fromDt, $toDt])
            ->get();

        $returns = $shop->breadReturns()
            ->with('breadCategory')
            ->whereBetween('date', [$fromDt, $toDt])
            ->get();

        $expenses = $shop->expenses()
            ->whereBetween('date', [$fromDt, $toDt])
            ->get();

        $totalBread = (int) $productions->sum('bread_produced');
        $totalProductionAmount = (float) $productions->sum(
            fn ($p) => $p->bread_produced * (float) $p->breadCategory->selling_price
        );

        $totalReturnsQty = (int) $returns->sum('quantity');
        $totalReturnsAmount = (float) $returns->sum('total_amount');

        $soldQuantity = $totalBread - $totalReturnsQty;
        $netSales = $totalProductionAmount - $totalReturnsAmount;

        $ingredientCost = (float) $productions->sum('ingredient_cost');
        $externalExpenses = (float) $expenses->sum('amount');
        $totalExpenses = $ingredientCost + $externalExpenses;

        $profit = $netSales - $totalExpenses;

        $expensesByCategory = $expenses->groupBy('category')
            ->map(fn ($group) => (float) $group->sum('amount'))
            ->toArray();
        // JSON da obyekt {} bo‘lsin — bo‘sh [] massiv Dart `Map` parse xatosiga olib kelardi.
        if ($expensesByCategory === []) {
            $expensesByCategory = new \stdClass;
        }

        $returnsByCategory = $this->buildReturnsByCategory($returns);
        $productBreakdown = $this->buildProductBreakdown($productions, $returns);

        return [
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
            'production' => [
                'total_flour_kg' => (float) $productions->sum('flour_used_kg'),
                'total_bread' => $totalBread,
                'ingredient_cost' => $ingredientCost,
                'count' => $productions->count(),
            ],
            // total_amount = tushum (brutto chiqim narxi) − vozvrat summasi (netto sotuv)
            'sales' => [
                'total_quantity' => $soldQuantity,
                'total_amount' => $netSales,
                'gross_amount' => $totalProductionAmount,
            ],
            'returns' => [
                'total_quantity' => $totalReturnsQty,
                'total_amount' => $totalReturnsAmount,
                'count' => $returns->count(),
            ],
            'net_sales' => $netSales,
            'expenses' => [
                'ingredient_cost' => $ingredientCost,
                'external' => $externalExpenses,
                'total' => $totalExpenses,
                'by_category' => $expensesByCategory,
            ],
            'profit' => $profit,
            'returns_by_category' => $returnsByCategory,
            'product_breakdown' => $productBreakdown,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\BreadReturn>  $returns
     * @return array<int, array{bread_category_id: string, name: string, quantity: int, total_amount: float, count: int}>
     */
    private function buildReturnsByCategory($returns): array
    {
        if ($returns->isEmpty()) {
            return [];
        }

        $rows = $returns->groupBy('bread_category_id')->map(function ($group) {
            $first = $group->first();

            return [
                'bread_category_id' => $first->bread_category_id,
                'name' => $first->breadCategory?->name ?? '',
                'quantity' => (int) $group->sum('quantity'),
                'total_amount' => round((float) $group->sum('total_amount'), 2),
                'count' => $group->count(),
            ];
        })->values()->all();

        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Production>  $productions
     * @param  \Illuminate\Support\Collection<int, \App\Models\BreadReturn>  $returns
     * @return array<int, array{bread_category_id: string, name: string, total_produced: int, gross_revenue: float, ingredient_cost: float, returns_quantity: int, returns_amount: float, net_revenue: float, profit: float}>
     */
    private function buildProductBreakdown($productions, $returns): array
    {
        $catIds = $productions->pluck('bread_category_id')
            ->merge($returns->pluck('bread_category_id'))
            ->unique()
            ->values();

        $out = [];
        foreach ($catIds as $catId) {
            $pRows = $productions->where('bread_category_id', $catId);
            $rRows = $returns->where('bread_category_id', $catId);

            $first = $pRows->first()?->breadCategory ?? $rRows->first()?->breadCategory;
            $name = $first?->name ?? '';

            $totalProduced = (int) $pRows->sum('bread_produced');
            $ingCost = round((float) $pRows->sum('ingredient_cost'), 2);
            $gross = round((float) $pRows->sum(function ($p) {
                return (int) $p->bread_produced * (float) $p->breadCategory->selling_price;
            }), 2);

            $retQty = (int) $rRows->sum('quantity');
            $retAmt = round((float) $rRows->sum('total_amount'), 2);
            $net = round($gross - $retAmt, 2);
            $profitCat = round($net - $ingCost, 2);

            $out[] = [
                'bread_category_id' => $catId,
                'name' => $name,
                'total_produced' => $totalProduced,
                'gross_revenue' => $gross,
                'ingredient_cost' => $ingCost,
                'returns_quantity' => $retQty,
                'returns_amount' => $retAmt,
                'net_revenue' => $net,
                'profit' => $profitCat,
            ];
        }

        usort($out, fn ($a, $b) => $b['gross_revenue'] <=> $a['gross_revenue']);

        return $out;
    }
}
