<?php

namespace Tests\Unit;

use App\Enums\ShopUserType;
use App\Models\BreadCategory;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\Shop;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Statistika sahifasi ma'lumotlari: kunlik grafik, umumiy summalar va
 * mahsulotning ASL tannarxi (xom ashyo + taqsimlangan tashqi xarajat).
 */
class ReportStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;

    private Shop $shop;

    private User $user;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ReportService();
        $this->today = now()->toDateString();
        $this->user = User::factory()->create();

        $this->shop = Shop::create([
            'name' => 'Test',
            'slug' => 'test-'.Str::random(5),
            'is_active' => true,
            'currency_id' => Currency::query()->where('code', 'UZS')->value('id'),
        ]);

        $this->user->shops()->attach($this->shop->id, ['user_type' => ShopUserType::Owner]);
    }

    private function makeCategory(string $name, float $price): BreadCategory
    {
        return BreadCategory::create([
            'shop_id' => $this->shop->id,
            'name' => $name,
            'selling_price' => $price,
            'currency_id' => Currency::query()->where('code', 'UZS')->value('id'),
        ]);
    }

    /** `productions.recipe_id` majburiy FK — har bir tur uchun retsept kerak. */
    private function recipeFor(BreadCategory $c): Recipe
    {
        // Bir tur uchun bitta retsept — takroriy yaratilsa unikal cheklov buziladi.
        return Recipe::firstOrCreate(
            ['shop_id' => $this->shop->id, 'bread_category_id' => $c->id],
            ['name' => $c->name, 'flour_amount_kg' => 1, 'output_quantity' => 1],
        );
    }

    private function produce(BreadCategory $c, int $qty, float $ingredientCost, ?string $date = null): void
    {
        Production::create([
            'shop_id' => $this->shop->id,
            'recipe_id' => $this->recipeFor($c)->id,
            'bread_category_id' => $c->id,
            'date' => $date ?? $this->today,
            'batch_count' => 1,
            'flour_used_kg' => 0,
            'bread_produced' => $qty,
            'ingredient_cost' => $ingredientCost,
            'created_by' => $this->user->id,
        ]);
    }

    private function spend(float $amount, ?string $date = null): void
    {
        Expense::create([
            'shop_id' => $this->shop->id,
            'category' => 'ijara',
            'amount' => $amount,
            'date' => $date ?? $this->today,
            'created_by' => $this->user->id,
        ]);
    }

    // ─── Umumiy summalar ─────────────────────────────────────────────────

    public function test_profit_is_income_minus_all_expenses(): void
    {
        $somsa = $this->makeCategory('Somsa', 5000);
        $this->produce($somsa, 200, 600000);
        $this->spend(300000);

        $stats = $this->service->statistics($this->shop, $this->today, $this->today);
        $t = $stats['totals'];

        $this->assertSame(1000000.0, $t['income']);          // 200 × 5000
        $this->assertSame(900000.0, $t['expense']);          // 600 000 + 300 000
        // Bosh sahifadagi foydadan farqli — tashqi xarajat ham ayriladi.
        $this->assertSame(100000.0, $t['profit']);
        $this->assertSame(600000.0, $t['ingredient_cost']);
        $this->assertSame(300000.0, $t['external_expenses']);
    }

    public function test_series_covers_every_day_including_empty_ones(): void
    {
        $somsa = $this->makeCategory('Somsa', 5000);
        $this->produce($somsa, 10, 20000, now()->subDays(2)->toDateString());

        $stats = $this->service->statistics(
            $this->shop,
            now()->subDays(4)->toDateString(),
            $this->today,
        );

        // 5 kun: bugundan 4 kun oldingacha
        $this->assertCount(5, $stats['series']);

        $byDate = collect($stats['series'])->keyBy('date');
        $active = $byDate[now()->subDays(2)->toDateString()];

        $this->assertSame(50000.0, $active['income']);
        $this->assertSame(20000.0, $active['expense']);
        $this->assertSame(30000.0, $active['profit']);

        // Ma'lumotsiz kun ham qatorda bo'lishi kerak — grafikda uzilish bo'lmasin.
        $empty = $byDate[$this->today];
        $this->assertSame(0.0, $empty['income']);
        $this->assertSame(0.0, $empty['profit']);
    }

    // ─── Asl tannarx ─────────────────────────────────────────────────────

    public function test_overhead_is_split_by_revenue_share(): void
    {
        $somsa = $this->makeCategory('Somsa', 5000);
        $tort = $this->makeCategory('Tort', 50000);

        // Somsa: 200 × 5 000 = 1 000 000 daromad (67%)
        // Tort :  10 × 50 000 =  500 000 daromad (33%)
        $this->produce($somsa, 200, 600000);   // 1 donaga 3 000 xom ashyo
        $this->produce($tort, 10, 200000);     // 1 donaga 20 000 xom ashyo
        $this->spend(300000);

        $stats = $this->service->statistics($this->shop, $this->today, $this->today);
        $byName = collect($stats['products'])->keyBy('name');

        $s = $byName['Somsa'];
        $t = $byName['Tort'];

        // Xom ashyo tannarxi o'zgarmaydi.
        $this->assertSame(3000.0, $s['ingredient_unit_cost']);
        $this->assertSame(20000.0, $t['ingredient_unit_cost']);

        // Tashqi xarajat daromad ulushi bo'yicha: 300 000 × 2/3 va × 1/3.
        // Somsa: 200 000 / 200 dona = 1 000
        // Tort  : 100 000 /  10 dona = 10 000
        $this->assertSame(1000.0, $s['overhead_unit_cost']);
        $this->assertSame(10000.0, $t['overhead_unit_cost']);

        $this->assertSame(4000.0, $s['true_unit_cost']);
        $this->assertSame(30000.0, $t['true_unit_cost']);

        // Taqsimlangan xarajat yig'indisi umumiy tashqi xarajatga teng bo'lsin.
        $allocated = $s['overhead_unit_cost'] * $s['quantity']
            + $t['overhead_unit_cost'] * $t['quantity'];
        $this->assertEqualsWithDelta(300000.0, $allocated, 1.0);
    }

    public function test_true_cost_equals_ingredient_cost_when_no_external_expenses(): void
    {
        $somsa = $this->makeCategory('Somsa', 5000);
        $this->produce($somsa, 100, 300000);

        $stats = $this->service->statistics($this->shop, $this->today, $this->today);
        $row = $stats['products'][0];

        $this->assertSame(3000.0, $row['ingredient_unit_cost']);
        $this->assertSame(0.0, $row['overhead_unit_cost']);
        $this->assertSame(3000.0, $row['true_unit_cost']);
    }

    public function test_zero_priced_products_do_not_break_allocation(): void
    {
        // Narxi 0 bo'lgan mahsulot daromad keltirmaydi — bo'lish xatosi
        // bo'lmasligi va unga xarajat yozilmasligi kerak.
        $free = $this->makeCategory('Namuna', 0);
        $this->produce($free, 50, 100000);
        $this->spend(200000);

        $stats = $this->service->statistics($this->shop, $this->today, $this->today);
        $row = $stats['products'][0];

        $this->assertSame(0.0, $row['overhead_unit_cost']);
        $this->assertSame(2000.0, $row['true_unit_cost']);
    }

    public function test_long_range_is_grouped_by_month(): void
    {
        // "Barchasi" tanlanganda kunlik nuqtalar minglab bo'lib ketardi —
        // uzun oraliq oylarga guruhlanadi.
        $somsa = $this->makeCategory('Somsa', 1000);
        $this->produce($somsa, 10, 5000, now()->subMonths(3)->toDateString());
        $this->produce($somsa, 20, 9000, now()->subMonths(1)->toDateString());

        $stats = $this->service->statistics(
            $this->shop,
            now()->subMonths(4)->toDateString(),
            $this->today,
        );

        $this->assertSame('month', $stats['granularity']);
        // 4 oy oldindan bugungacha — 5 ta oy nuqtasi.
        $this->assertCount(5, $stats['series']);

        foreach ($stats['series'] as $point) {
            $this->assertSame('01', substr($point['date'], -2), 'oy boshiga tushishi kerak');
        }
    }

    public function test_short_range_stays_daily(): void
    {
        $somsa = $this->makeCategory('Somsa', 1000);
        $this->produce($somsa, 10, 5000);

        $stats = $this->service->statistics(
            $this->shop,
            now()->subDays(6)->toDateString(),
            $this->today,
        );

        $this->assertSame('day', $stats['granularity']);
        $this->assertCount(7, $stats['series']);
    }

    public function test_products_are_sorted_by_quantity(): void
    {
        $a = $this->makeCategory('Kam', 1000);
        $b = $this->makeCategory('Ko\'p', 1000);
        $this->produce($a, 5, 1000);
        $this->produce($b, 90, 9000);

        $stats = $this->service->statistics($this->shop, $this->today, $this->today);

        $this->assertSame('Ko\'p', $stats['products'][0]['name']);
        $this->assertSame('Kam', $stats['products'][1]['name']);
    }
}
