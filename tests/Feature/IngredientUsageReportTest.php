<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Models\BreadCategory;
use App\Models\Currency;
use App\Models\Ingredient;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IngredientUsageReportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Shop $shop;
    private string $uzsId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uzsId = Currency::query()->where('code', 'UZS')->value('id');
        $this->owner = User::factory()->create();
        $this->shop = Shop::create([
            'name' => 'Nonvoyxona',
            'slug' => 'shop-' . Str::random(6),
            'is_active' => true,
            'currency_id' => $this->uzsId,
        ]);
        $this->owner->shops()->attach($this->shop->id, ['user_type' => ShopUserType::Owner]);
    }

    private function ingredient(string $name, float $price, bool $flour = false): Ingredient
    {
        return Ingredient::create([
            'shop_id' => $this->shop->id,
            'name' => $name,
            'unit' => 'kg',
            'price_per_unit' => $price,
            'currency_id' => $this->uzsId,
            'is_flour' => $flour,
        ]);
    }

    /** @param  array<string, float>  $ingredients  ingredient_id => miqdor (1 partiya) */
    private function produce(string $product, array $ingredients, float $batches, string $date): void
    {
        $category = BreadCategory::create([
            'shop_id' => $this->shop->id,
            'name' => $product,
            'selling_price' => 5000,
            'currency_id' => $this->uzsId,
        ]);
        $recipe = Recipe::create([
            'shop_id' => $this->shop->id,
            'bread_category_id' => $category->id,
            'name' => $product,
            'flour_amount_kg' => 0,
            'output_quantity' => 100,
            'is_active' => true,
        ]);
        foreach ($ingredients as $id => $qty) {
            $recipe->recipeIngredients()->create(['ingredient_id' => $id, 'quantity' => $qty]);
        }
        Production::create([
            'shop_id' => $this->shop->id,
            'recipe_id' => $recipe->id,
            'bread_category_id' => $category->id,
            'date' => $date,
            'batch_count' => $batches,
            'flour_used_kg' => 0,
            'bread_produced' => 100,
            'ingredient_cost' => 0,
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_usage_sums_ingredients_across_productions_for_the_day(): void
    {
        $un = $this->ingredient('Un', 5000, true);
        $tuz = $this->ingredient('Tuz', 2000);

        // Non: 2 partiya × (25 kg un, 0.5 kg tuz); Patir: 1 partiya × 10 kg un.
        $this->produce('Non', [$un->id => 25, $tuz->id => 0.5], 2, '2026-09-16');
        $this->produce('Patir', [$un->id => 10], 1, '2026-09-16');
        // Boshqa kun — hisobga kirmaydi.
        $this->produce('Somsa', [$un->id => 99], 1, '2026-09-15');

        $usage = $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/reports/ingredients?date=2026-09-16")
            ->assertOk()
            ->json('data.usage');

        $this->assertSame(2, $usage['production_count']);
        // Un: 50 + 10 = 60 kg × 5000 = 300 000; Tuz: 1 kg × 2000 = 2 000.
        $this->assertSame(302000.0, (float) $usage['total_cost']);
        $this->assertCount(2, $usage['items']);

        $un = $usage['items'][0];
        $this->assertSame('Un', $un['name']);
        $this->assertSame(60.0, (float) $un['quantity']);
        $this->assertSame(300000.0, (float) $un['cost']);
        $this->assertSame('kg', $un['unit']);
        $this->assertTrue($un['is_flour']);
        $this->assertSame('Non', $un['by_product'][0]['name']);
        $this->assertSame(50.0, (float) $un['by_product'][0]['quantity']);
        $this->assertSame('Patir', $un['by_product'][1]['name']);

        $this->assertSame('Tuz', $usage['items'][1]['name']);
        $this->assertSame(1.0, (float) $usage['items'][1]['quantity']);
    }

    public function test_empty_day_returns_no_items(): void
    {
        $usage = $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/reports/ingredients?date=2026-09-16")
            ->assertOk()
            ->json('data.usage');

        $this->assertSame([], $usage['items']);
        $this->assertSame(0.0, (float) $usage['total_cost']);
    }
}
