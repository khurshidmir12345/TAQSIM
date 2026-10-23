<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Models\BreadCategory;
use App\Models\Currency;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Shop;
use App\Models\User;
use App\Services\ProductionService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Retseptda ishlatilgan xom ashyo o'chirilsa ham retseptlar ro'yxati ochilishi
 * va ishlab chiqarish hisoblanishi kerak.
 *
 * Prodda: xom ashyo o'chirilgach `Recipe::totalCost()` null'dan narx o'qib
 * yiqilardi — retseptlar ro'yxati butunlay ochilmas, yangi retsept qo'shganda
 * ham "xatolik" chiqardi (retsept saqlanardi, lekin ko'rinmasdi).
 */
class DeletedIngredientRecipeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Shop $shop;
    private Recipe $recipe;
    private Ingredient $un;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CurrencySeeder::class);

        $uzs = Currency::query()->where('code', 'UZS')->value('id');
        $this->owner = User::factory()->create();
        $this->shop = Shop::create(['name' => 'Non', 'slug' => 'non-' . Str::random(5), 'is_active' => true, 'currency_id' => $uzs]);
        $this->owner->shops()->attach($this->shop->id, ['user_type' => ShopUserType::Owner]);

        $product = BreadCategory::create(['shop_id' => $this->shop->id, 'name' => "Yog'li non", 'selling_price' => 3500, 'currency_id' => $uzs]);
        $this->un = Ingredient::create(['shop_id' => $this->shop->id, 'name' => 'Un', 'unit' => 'kg', 'price_per_unit' => 5900, 'currency_id' => $uzs, 'is_flour' => true]);
        $tuz = Ingredient::create(['shop_id' => $this->shop->id, 'name' => 'Tuz', 'unit' => 'kg', 'price_per_unit' => 2000, 'currency_id' => $uzs]);

        $this->recipe = Recipe::create([
            'shop_id' => $this->shop->id,
            'bread_category_id' => $product->id,
            'name' => "Yog'li non",
            'output_quantity' => 194,
            'is_active' => true,
        ]);
        $this->recipe->recipeIngredients()->create(['ingredient_id' => $this->un->id, 'quantity' => 50]);
        $this->recipe->recipeIngredients()->create(['ingredient_id' => $tuz->id, 'quantity' => 1]);

        $this->un->delete();
    }

    public function test_recipe_list_still_opens_after_ingredient_is_deleted(): void
    {
        $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/recipes")
            ->assertOk()
            // O'chirilgan xom ashyo oxirgi narxi bilan hisoblanadi: 50×5900 + 1×2000.
            ->assertJsonPath('data.recipes.0.total_cost', 297000);
    }

    public function test_production_is_still_calculated(): void
    {
        $service = app(ProductionService::class);
        $recipe = $this->recipe->fresh();

        $this->assertEqualsWithDelta(297000, $service->calculateIngredientCost($recipe, 1), 0.01);
        $this->assertEqualsWithDelta(50, $service->calculateFlourUsed($recipe, 1), 0.001);
    }
}
