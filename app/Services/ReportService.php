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

    /**
     * Har bir bo'lim uchun alohida umumiy summalar.
     *
     * production — faqat ishlab chiqarish: daromad, ingredient xarajati, foyda
     * returns    — faqat vozvratlar summasi
     * expenses   — faqat tashqi (kassa) xarajatlar summasi
     */
    public function summary(Shop $shop): array
    {
        $productions = $shop->productions()->with('breadCategory')->get();
        $returns     = $shop->breadReturns()->get();
        $expenses    = $shop->expenses()->get();

        $prodIncome  = (float) $productions->sum(
            fn ($p) => $p->bread_produced * (float) $p->breadCategory->selling_price
        );
        $prodExpense = (float) $productions->sum('ingredient_cost');
        $prodProfit  = $prodIncome - $prodExpense;

        return [
            'production' => [
                'income'  => round($prodIncome, 2),
                'expense' => round($prodExpense, 2),
                'profit'  => round($prodProfit, 2),
            ],
            'returns_total'    => round((float) $returns->sum('total_amount'), 2),
            'expenses_total'   => round((float) $expenses->sum('amount'), 2),
        ];
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

        // Asosiy sahifa faqat ishlab chiqarish va vozvrat haqida gapiradi:
        // foyda netto sotuvdan xom ashyo qiymatini ayirib topiladi. Tashqi
        // xarajatlar (ijara, yoqilg'i va h.k.) kassaning ishi — ular bu yerda
        // hisobga olinsa, bir kun ijara to'langani uchun nonvoyxona zarar
        // ko'rsatgandek ko'rinardi.
        $profit = $netSales - $ingredientCost;

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

    /**
     * Statistika sahifasi uchun yagona javob: kunlik grafik, umumiy summalar
     * va mahsulotning ASL tannarxi.
     *
     * Bu yerdagi `profit` bosh sahifadagidan FARQ QILADI: bu yerda tashqi
     * xarajatlar ham ayriladi (`daromad − barcha xarajat`), chunki grafikda
     * uch ko'rsatkich yonma-yon turadi va foyda + xarajat = daromad bo'lishi
     * kerak. Bosh sahifadagi foyda esa faqat xom ashyodan keyingi yalpi foyda.
     *
     * @return array{series: array<int,array<string,mixed>>, totals: array<string,float>, products: array<int,array<string,mixed>>}
     */
    /**
     * Do'kondagi eng birinchi yozuv sanasi — "Barchasi" davri shu yerdan
     * boshlanadi. Aks holda oraliqni qattiq sana bilan belgilashga to'g'ri
     * kelardi va grafik yillab bo'sh nuqtalar bilan boshlanardi.
     */
    public function earliestActivityDate(Shop $shop): string
    {
        $dates = array_filter([
            $shop->productions()->min('date'),
            $shop->breadReturns()->min('date'),
            $shop->expenses()->min('date'),
        ]);

        if ($dates === []) {
            return $shop->created_at?->toDateString() ?? now()->toDateString();
        }

        return Carbon::parse(min($dates))->toDateString();
    }

    public function statistics(Shop $shop, string $from, string $to): array
    {
        $fromDt = Carbon::parse($from)->startOfDay();
        $toDt = Carbon::parse($to)->endOfDay();

        $productions = $shop->productions()
            ->with('breadCategory')
            ->whereBetween('date', [$fromDt, $toDt])
            ->get();

        $returns = $shop->breadReturns()
            ->whereBetween('date', [$fromDt, $toDt])
            ->get();

        $expenses = $shop->expenses()
            ->whereBetween('date', [$fromDt, $toDt])
            ->get();

        // Uzun oraliqda kunlik nuqtalar minglab bo'lib ketadi va grafik
        // o'qib bo'lmaydi — shuning uchun oylarga guruhlanadi.
        $monthly = $fromDt->diffInDays($toDt) > 62;

        $bucket = static fn ($model): string => $monthly
            ? Carbon::parse($model->date)->startOfMonth()->toDateString()
            : Carbon::parse($model->date)->toDateString();

        $prodByDay = $productions->groupBy($bucket);
        $retByDay = $returns->groupBy($bucket);
        $expByDay = $expenses->groupBy($bucket);

        // Har bir kun uchun yozuv — ma'lumot bo'lmagan kunlar ham nol bilan
        // qatnashadi, aks holda grafikda uzilish paydo bo'lardi.
        $series = [];
        $cursor = $monthly
            ? $fromDt->copy()->startOfMonth()
            : $fromDt->copy()->startOfDay();
        $last = $toDt->copy()->startOfDay();

        while ($cursor->lte($last)) {
            $key = $cursor->toDateString();

            $gross = (float) ($prodByDay[$key] ?? collect())->sum(
                fn ($p) => $p->bread_produced * (float) ($p->breadCategory->selling_price ?? 0)
            );
            $returned = (float) ($retByDay[$key] ?? collect())->sum('total_amount');
            $ingredient = (float) ($prodByDay[$key] ?? collect())->sum('ingredient_cost');
            $external = (float) ($expByDay[$key] ?? collect())->sum('amount');

            $income = $gross - $returned;
            $expense = $ingredient + $external;

            $series[] = [
                'date' => $key,
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'profit' => round($income - $expense, 2),
            ];

            $monthly ? $cursor->addMonth() : $cursor->addDay();
        }

        $totalGross = (float) $productions->sum(
            fn ($p) => $p->bread_produced * (float) ($p->breadCategory->selling_price ?? 0)
        );
        $totalReturns = (float) $returns->sum('total_amount');
        $totalIngredient = (float) $productions->sum('ingredient_cost');
        $totalExternal = (float) $expenses->sum('amount');

        $totalIncome = $totalGross - $totalReturns;
        $totalExpense = $totalIngredient + $totalExternal;

        return [
            'period' => ['from' => $fromDt->toDateString(), 'to' => $toDt->toDateString()],
            // Ilova o'q yorliqlarini shu bo'yicha formatlaydi (kun yoki oy).
            'granularity' => $monthly ? 'month' : 'day',
            'series' => $series,
            'totals' => [
                'income' => round($totalIncome, 2),
                'expense' => round($totalExpense, 2),
                'profit' => round($totalIncome - $totalExpense, 2),
                'ingredient_cost' => round($totalIngredient, 2),
                'external_expenses' => round($totalExternal, 2),
                'returns' => round($totalReturns, 2),
            ],
            'products' => $this->trueUnitCosts($productions, $totalExternal, $totalGross),
        ];
    }

    /**
     * Mahsulotning ASL tannarxi: xom ashyo + unga to'g'ri keladigan tashqi xarajat.
     *
     * Tashqi xarajatlar (ijara, yoqilg'i, ish haqi) DAROMAD ULUSHI bo'yicha
     * taqsimlanadi — ko'proq pul keltirgan mahsulot ko'proq yuk ko'taradi.
     * Miqdor bo'yicha teng bo'lish arzon va qimmat mahsulotni bir xil yuklab,
     * qimmat mahsulotning tannarxini sun'iy pasaytirardi.
     *
     * @return array<int,array<string,mixed>>
     */
    private function trueUnitCosts($productions, float $totalExternal, float $totalGross): array
    {
        $rows = [];

        foreach ($productions->groupBy('bread_category_id') as $items) {
            $category = $items->first()->breadCategory;

            if (! $category) {
                continue;
            }

            $quantity = (int) $items->sum('bread_produced');

            if ($quantity <= 0) {
                continue;
            }

            $sellingPrice = (float) $category->selling_price;
            $ingredientTotal = (float) $items->sum('ingredient_cost');
            $revenue = $quantity * $sellingPrice;

            // Daromad bo'lmasa (narx 0) taqsimlashning asosi yo'q — 0 beriladi.
            $share = $totalGross > 0 ? $revenue / $totalGross : 0.0;
            $overheadTotal = $totalExternal * $share;

            $rows[] = [
                'bread_category_id' => $category->id,
                'name' => $category->name,
                'quantity' => $quantity,
                'selling_price' => round($sellingPrice, 2),
                // Faqat xom ashyodan kelib chiqqan tannarx (hisoblash sahifasidagi).
                'ingredient_unit_cost' => round($ingredientTotal / $quantity, 2),
                // Shu mahsulotga to'g'ri kelgan tashqi xarajat, 1 donaga.
                'overhead_unit_cost' => round($overheadTotal / $quantity, 2),
                // Asl tannarx — ikkalasining yig'indisi.
                'true_unit_cost' => round(($ingredientTotal + $overheadTotal) / $quantity, 2),
                'overhead_share' => round($share * 100, 1),
            ];
        }

        // Eng ko'p ishlab chiqarilgani tepada.
        usort($rows, static fn (array $a, array $b): int => $b['quantity'] <=> $a['quantity']);

        return $rows;
    }
}
