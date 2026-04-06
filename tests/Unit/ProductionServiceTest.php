<?php

namespace Tests\Unit;

use App\Models\BreadCategory;
use App\Models\Currency;
use App\Models\Ingredient;
use App\Models\MeasurementUnit;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Shop;
use App\Models\User;
use App\Services\ProductionService;
use Database\Seeders\MeasurementUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductionService $service;
    private Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MeasurementUnitSeeder::class);

        $this->service = new ProductionService();

        $user = User::factory()->create();
        $uzsId = Currency::query()->where('code', 'UZS')->value('id');
        $muKg = MeasurementUnit::query()->where('code', 'kg')->value('id');
        $muLitr = MeasurementUnit::query()->where('code', 'l')->value('id');
        $muQop = MeasurementUnit::query()->where('code', 'qop')->value('id');
        $shop = Shop::create([
            'name' => 'Test Shop',
            'slug' => 'test-' . Str::random(5),
            'is_active' => true,
            'currency_id' => $uzsId,
        ]);

        $category = BreadCategory::create([
            'shop_id' => $shop->id,
            'name' => 'Test tur',
            'selling_price' => 1000,
            'currency_id' => $uzsId,
        ]);

        $flour = Ingredient::create([
            'shop_id' => $shop->id,
            'name' => 'Un',
            'unit' => 'kg',
            'measurement_unit_id' => $muKg,
            'is_flour' => true,
            'price_per_unit' => 8000,
            'currency_id' => $uzsId,
        ]);

        $water = Ingredient::create([
            'shop_id' => $shop->id,
            'name' => 'Suv',
            'unit' => 'litr',
            'measurement_unit_id' => $muLitr,
            'is_flour' => false,
            'price_per_unit' => 500,
            'currency_id' => $uzsId,
        ]);

        $this->recipe = Recipe::create([
            'shop_id' => $shop->id,
            'bread_category_id' => $category->id,
            'measurement_unit_id' => $muQop,
            'name' => 'Test retsept',
            'output_quantity' => 100,
            'is_active' => true,
        ]);

        RecipeIngredient::create([
            'recipe_id' => $this->recipe->id,
            'ingredient_id' => $flour->id,
            'quantity' => 50,
        ]);

        RecipeIngredient::create([
            'recipe_id' => $this->recipe->id,
            'ingredient_id' => $water->id,
            'quantity' => 30,
        ]);
    }

    public function test_calculate_ingredient_cost(): void
    {
        $cost = $this->service->calculateIngredientCost($this->recipe, 1);

        // 50kg * 8000 + 30L * 500 = 400000 + 15000 = 415000
        $this->assertEquals(415000, $cost);
    }

    public function test_calculate_ingredient_cost_with_batch(): void
    {
        $cost = $this->service->calculateIngredientCost($this->recipe, 2);

        $this->assertEquals(830000, $cost);
    }

    public function test_calculate_bread_output(): void
    {
        $output = $this->service->calculateBreadOutput($this->recipe, 1);

        $this->assertEquals(100, $output);
    }

    public function test_calculate_bread_output_with_batch(): void
    {
        $output = $this->service->calculateBreadOutput($this->recipe, 2.5);

        $this->assertEquals(250, $output);
    }

    public function test_calculate_flour_used(): void
    {
        $flour = $this->service->calculateFlourUsed($this->recipe, 1);

        $this->assertEquals(50, $flour);
    }

    public function test_calculate_flour_used_with_batch(): void
    {
        $flour = $this->service->calculateFlourUsed($this->recipe, 3);

        $this->assertEquals(150, $flour);
    }
}
