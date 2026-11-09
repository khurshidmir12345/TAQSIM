<?php

namespace Tests\Feature;

use App\Enums\CashTransactionSource;
use App\Enums\ShopUserType;
use App\Models\BreadCategory;
use App\Models\CashTransaction;
use App\Models\Currency;
use App\Models\Ingredient;
use App\Models\MeasurementUnit;
use App\Models\Production;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\MeasurementUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Xom ashyo ombori: kirim kassaga, chiqim ombordan (kassaga emas).
 */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Shop $shop;
    private Ingredient $un;
    private string $recipeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MeasurementUnitSeeder::class);

        $uzs = Currency::query()->where('code', 'UZS')->value('id');
        $qop = MeasurementUnit::query()->where('type', 'batch')
            ->whereIn('code', MeasurementUnit::BATCH_UNIT_CODES)->value('id');

        $this->owner = User::factory()->create();
        $this->shop = Shop::create([
            'name' => 'Nonvoyxona',
            'slug' => 'shop-' . Str::random(6),
            'is_active' => true,
            'currency_id' => $uzs,
            'cash_track_production' => true,
        ]);
        $this->owner->shops()->attach($this->shop->id, ['user_type' => ShopUserType::Owner]);

        $non = BreadCategory::create([
            'shop_id' => $this->shop->id, 'name' => 'Non', 'selling_price' => 4000, 'currency_id' => $uzs,
        ]);
        $this->un = Ingredient::create([
            'shop_id' => $this->shop->id, 'name' => 'Un', 'unit' => 'kg',
            'price_per_unit' => 6000, 'currency_id' => $uzs, 'is_flour' => true,
        ]);

        $this->recipeId = $this->actingAs($this->owner)
            ->postJson($this->url('recipes'), [
                'name' => 'Non',
                'bread_category_id' => $non->id,
                'measurement_unit_id' => $qop,
                'output_quantity' => 100,
                'ingredients' => [['ingredient_id' => $this->un->id, 'quantity' => 50]],
            ])->assertCreated()->json('data.recipe.id');
    }

    private function url(string $path): string
    {
        return "/api/v1/shops/{$this->shop->id}/{$path}";
    }

    private function produce(float $batch): string
    {
        return $this->actingAs($this->owner)
            ->postJson($this->url('productions'), [
                'recipe_id' => $this->recipeId,
                'date' => now()->toDateString(),
                'batch_count' => $batch,
                'items' => [['bread_category_id' => BreadCategory::first()->id, 'quantity' => (int) (100 * $batch)]],
            ])->assertCreated()->json('data.production.id');
    }

    private function cashCost(string $productionId): float
    {
        return (float) CashTransaction::query()
            ->where('source', CashTransactionSource::Production->value)
            ->where('source_id', $productionId)
            ->where('type', 'expense')
            ->sum('amount');
    }

    public function test_purchase_goes_to_cash_and_consumption_does_not(): void
    {
        // Ombor yo'q — chiqimda xom ashyo puli kassaga yoziladi (avvalgidek).
        $before = $this->produce(1);
        $this->assertEquals(300000, $this->cashCost($before));

        // 100 kg un 700 000 so'mga.
        $res = $this->actingAs($this->owner)
            ->postJson($this->url('inventory/purchases'), [
                'ingredient_id' => $this->un->id,
                'quantity' => 100,
                'total_amount' => 700000,
            ])->assertCreated();
        $this->assertEquals(100, $res->json('data.ingredient.stock_quantity'));
        // Qoldiq yo'q edi — narx kirim narxiga teng.
        $this->assertEquals(7000, (float) $res->json('data.ingredient.price_per_unit'));
        $this->assertEquals(700000, (float) CashTransaction::query()
            ->where('source', CashTransactionSource::IngredientPurchase->value)->sum('amount'));

        // Endi chiqim: ombordan 50 kg, kassaga xom ashyo chiqimi yo'q.
        $after = $this->produce(1);
        $this->assertEquals(0, $this->cashCost($after));
        $this->assertEquals(50, (float) $this->un->fresh()->stock_quantity);
        // Tannarx asosiy sahifa uchun saqlanadi.
        $this->assertEquals(350000, (float) Production::find($after)->ingredient_cost);
        // Eski chiqim kassada o'zgarmaydi.
        $this->assertEquals(300000, $this->cashCost($before));

        // Chiqim tahrirlansa ombor qayta hisoblanadi.
        $this->actingAs($this->owner)
            ->putJson($this->url("productions/{$after}"), ['batch_count' => 0.5])
            ->assertOk();
        $this->assertEquals(75, (float) $this->un->fresh()->stock_quantity);

        // O'chirilsa — qaytadi.
        $this->actingAs($this->owner)->deleteJson($this->url("productions/{$after}"))->assertOk();
        $this->assertEquals(100, (float) $this->un->fresh()->stock_quantity);
    }

    public function test_weighted_average_price_and_adjustment(): void
    {
        $this->actingAs($this->owner)->postJson($this->url('inventory/purchases'), [
            'ingredient_id' => $this->un->id, 'quantity' => 100, 'total_amount' => 600000,
        ])->assertCreated();
        $this->actingAs($this->owner)->postJson($this->url('inventory/purchases'), [
            'ingredient_id' => $this->un->id, 'quantity' => 100, 'total_amount' => 800000,
        ])->assertCreated();
        $this->assertEquals(7000, (float) $this->un->fresh()->price_per_unit);
        $this->assertEquals(200, (float) $this->un->fresh()->stock_quantity);

        // Sanash: aslida 190 kg — farq −10 yoziladi, kassaga tegmaydi.
        $this->actingAs($this->owner)->postJson($this->url('inventory/adjustments'), [
            'ingredient_id' => $this->un->id, 'actual_quantity' => 190,
        ])->assertOk()->assertJsonPath('data.movement.quantity', -10);
        $this->assertEquals(190, (float) $this->un->fresh()->stock_quantity);
        $this->assertSame(2, CashTransaction::query()->count());

        $this->actingAs($this->owner)->getJson($this->url('inventory'))
            ->assertOk()
            ->assertJsonPath('data.total_value', 1330000);
    }

    public function test_history_search_filter_and_delete_purchase(): void
    {
        $id = $this->actingAs($this->owner)->postJson($this->url('inventory/purchases'), [
            'ingredient_id' => $this->un->id, 'quantity' => 50, 'total_amount' => 300000, 'note' => 'Bozor',
        ])->json('data.movement.id');
        $this->produce(0.5);

        $this->actingAs($this->owner)->getJson($this->url('inventory/movements?type=purchase'))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ingredient_name', 'Un');
        $this->actingAs($this->owner)->getJson($this->url('inventory/movements?search=bozor'))
            ->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($this->owner)->getJson($this->url('inventory/movements?search=shakar'))
            ->assertOk()->assertJsonCount(0, 'data');

        $consumption = $this->actingAs($this->owner)
            ->getJson($this->url('inventory/movements?type=consumption'))->json('data.0.id');
        $this->actingAs($this->owner)->deleteJson($this->url("inventory/movements/{$consumption}"))
            ->assertUnprocessable();

        $this->actingAs($this->owner)->deleteJson($this->url("inventory/movements/{$id}"))->assertOk();
        $this->assertEquals(-25, (float) $this->un->fresh()->stock_quantity);
        $this->assertSame(0, CashTransaction::query()
            ->where('source', CashTransactionSource::IngredientPurchase->value)->count());
    }
}
