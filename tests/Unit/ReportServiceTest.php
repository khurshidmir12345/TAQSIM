<?php

namespace Tests\Unit;

use App\Enums\ShopUserType;
use App\Models\BreadCategory;
use App\Models\Currency;
use App\Models\BreadReturn;
use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\MeasurementUnit;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Shop;
use App\Models\User;
use App\Services\ReportService;
use Database\Seeders\MeasurementUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;
    private Shop $shop;
    private User $user;
    private BreadCategory $category;
    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MeasurementUnitSeeder::class);

        $this->service = new ReportService();
        $this->today = now()->toDateString();

        $this->user = User::factory()->create();
        $uzsId = Currency::query()->where('code', 'UZS')->value('id');
        $this->shop = Shop::create([
            'name' => 'Test',
            'slug' => 'test-' . Str::random(5),
            'is_active' => true,
            'currency_id' => $uzsId,
        ]);

        $this->user->shops()->attach($this->shop->id, ['user_type' => ShopUserType::Owner]);

        $this->category = BreadCategory::create([
            'shop_id' => $this->shop->id,
            'name' => 'Oq non',
            'selling_price' => 4000,
            'currency_id' => $uzsId,
        ]);

        $muKg = MeasurementUnit::query()->where('code', 'kg')->value('id');
        $flour = Ingredient::create([
            'shop_id' => $this->shop->id,
            'name' => 'Un',
            'unit' => 'kg',
            'measurement_unit_id' => $muKg,
            'is_flour' => true,
            'price_per_unit' => 8000,
            'currency_id' => $uzsId,
        ]);

        $muQop = MeasurementUnit::query()->where('code', 'qop')->value('id');

        $recipe = Recipe::create([
            'shop_id' => $this->shop->id,
            'bread_category_id' => $this->category->id,
            'measurement_unit_id' => $muQop,
            'name' => 'Test',
            'output_quantity' => 100,
        ]);

        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $flour->id,
            'quantity' => 50,
        ]);

        $production = Production::create([
            'shop_id' => $this->shop->id,
            'recipe_id' => $recipe->id,
            'bread_category_id' => $this->category->id,
            'date' => $this->today,
            'batch_count' => 1,
            'flour_used_kg' => 50,
            'bread_produced' => 100,
            'ingredient_cost' => 400000,
            'created_by' => $this->user->id,
        ]);

        BreadReturn::create([
            'shop_id' => $this->shop->id,
            'bread_category_id' => $this->category->id,
            'production_id' => $production->id,
            'date' => $this->today,
            'quantity' => 5,
            'price_per_unit' => 4000,
            'total_amount' => 20000,
            'created_by' => $this->user->id,
        ]);

        Expense::create([
            'shop_id' => $this->shop->id,
            'category' => 'transport',
            'amount' => 50000,
            'date' => $this->today,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_daily_report_has_correct_structure(): void
    {
        $report = $this->service->daily($this->shop, $this->today);

        $this->assertArrayHasKey('period', $report);
        $this->assertArrayHasKey('production', $report);
        $this->assertArrayHasKey('sales', $report);
        $this->assertArrayHasKey('returns', $report);
        $this->assertArrayHasKey('expenses', $report);
        $this->assertArrayHasKey('profit', $report);
        $this->assertArrayHasKey('returns_by_category', $report);
        $this->assertArrayHasKey('product_breakdown', $report);
    }

    public function test_daily_report_calculates_correctly(): void
    {
        $report = $this->service->daily($this->shop, $this->today);

        $this->assertEquals(100, $report['production']['total_bread']);
        $this->assertEquals(50, $report['production']['total_flour_kg']);
        $this->assertEquals(5, $report['returns']['total_quantity']);
        $this->assertEquals(95, $report['sales']['total_quantity']);
        $this->assertEquals(50000, $report['expenses']['external']);
    }

    public function test_range_report_works(): void
    {
        $report = $this->service->range($this->shop, $this->today, $this->today);

        $this->assertEquals($this->today, $report['period']['from']);
        $this->assertEquals($this->today, $report['period']['to']);
        $this->assertEquals(100, $report['production']['total_bread']);
    }
}
