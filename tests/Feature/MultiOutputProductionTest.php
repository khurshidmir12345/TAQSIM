<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Models\BreadCategory;
use App\Models\BreadReturn;
use App\Models\Currency;
use App\Models\Ingredient;
use App\Models\MeasurementUnit;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\MeasurementUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Bitta partiyadan bir nechta mahsulot: retsept, chiqim, hisobot, vozvrat.
 */
class MultiOutputProductionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Shop $shop;
    private string $uzsId;
    private string $qopId;
    private BreadCategory $non;
    private BreadCategory $somsa;
    private BreadCategory $patir;
    private Ingredient $un;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MeasurementUnitSeeder::class);

        $this->uzsId = Currency::query()->where('code', 'UZS')->value('id');
        $this->qopId = MeasurementUnit::query()
            ->where('type', 'batch')
            ->whereIn('code', MeasurementUnit::BATCH_UNIT_CODES)
            ->value('id');
        $this->owner = User::factory()->create();
        $this->shop = Shop::create([
            'name' => 'Nonvoyxona',
            'slug' => 'shop-' . Str::random(6),
            'is_active' => true,
            'currency_id' => $this->uzsId,
            'cash_track_production' => true,
        ]);
        $this->owner->shops()->attach($this->shop->id, ['user_type' => ShopUserType::Owner]);

        $this->non = $this->category('Non', 4000);
        $this->somsa = $this->category('Somsa', 8000);
        $this->patir = $this->category('Patir', 6000);

        // 1 qop: 50 kg un × 6000 = 300 000 so'm.
        $this->un = Ingredient::create([
            'shop_id' => $this->shop->id,
            'name' => 'Un',
            'unit' => 'kg',
            'price_per_unit' => 6000,
            'currency_id' => $this->uzsId,
            'is_flour' => true,
        ]);
    }

    private function category(string $name, float $price): BreadCategory
    {
        return BreadCategory::create([
            'shop_id' => $this->shop->id,
            'name' => $name,
            'selling_price' => $price,
            'currency_id' => $this->uzsId,
        ]);
    }

    private function url(string $path): string
    {
        return "/api/v1/shops/{$this->shop->id}/{$path}";
    }

    /** Non 80 + Somsa 40 / qop. */
    private function createMultiRecipe(): array
    {
        return $this->actingAs($this->owner)
            ->postJson($this->url('recipes'), [
                'kind' => 'mix',
                'name' => 'Oddiy xamir',
                'measurement_unit_id' => $this->qopId,
                'outputs' => [
                    ['bread_category_id' => $this->non->id, 'quantity' => 80],
                    ['bread_category_id' => $this->somsa->id, 'quantity' => 40],
                ],
                'ingredients' => [['ingredient_id' => $this->un->id, 'quantity' => 50]],
            ])
            ->assertCreated()
            ->json('data.recipe');
    }

    public function test_mix_recipe_has_no_primary_product(): void
    {
        $recipe = $this->createMultiRecipe();

        $this->assertSame('mix', $recipe['kind']);
        $this->assertCount(2, $recipe['outputs']);
        // Eski ilova aralash partiyani mahsulot retsepti deb olmasin.
        $this->assertNull($recipe['bread_category']);
        $this->assertSame(120, $recipe['output_quantity']);
        $this->assertSame($this->somsa->id, $recipe['outputs'][1]['bread_category_id']);
        $this->assertSame(40, $recipe['outputs'][1]['quantity']);
    }

    private function singleRecipe(BreadCategory $cat, int $output): array
    {
        return $this->actingAs($this->owner)
            ->postJson($this->url('recipes'), [
                'name' => $cat->name,
                'bread_category_id' => $cat->id,
                'measurement_unit_id' => $this->qopId,
                'output_quantity' => $output,
                'ingredients' => [['ingredient_id' => $this->un->id, 'quantity' => 10]],
            ])
            ->assertCreated()
            ->json('data.recipe');
    }

    public function test_mix_may_reuse_products_that_have_their_own_recipe(): void
    {
        $this->singleRecipe($this->non, 80);
        $this->singleRecipe($this->somsa, 40);
        $this->singleRecipe($this->patir, 60);

        // Aralash partiyalar istalgan mahsulotlar to'plami bo'la oladi.
        $this->createMultiRecipe();
        $this->actingAs($this->owner)
            ->postJson($this->url('recipes'), [
                'kind' => 'mix',
                'name' => 'Patir + Non',
                'measurement_unit_id' => $this->qopId,
                'outputs' => [
                    ['bread_category_id' => $this->patir->id, 'quantity' => 50],
                    ['bread_category_id' => $this->non->id, 'quantity' => 100],
                ],
                'ingredients' => [['ingredient_id' => $this->un->id, 'quantity' => 45]],
            ])
            ->assertCreated();

        $this->assertSame(5, Recipe::query()->count());
    }

    public function test_single_recipe_stays_unique_and_mix_needs_two_products(): void
    {
        $this->singleRecipe($this->non, 80);

        $this->actingAs($this->owner)
            ->postJson($this->url('recipes'), [
                'name' => 'Non 2',
                'bread_category_id' => $this->non->id,
                'measurement_unit_id' => $this->qopId,
                'output_quantity' => 50,
                'ingredients' => [['ingredient_id' => $this->un->id, 'quantity' => 10]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('bread_category_id');

        $this->actingAs($this->owner)
            ->postJson($this->url('recipes'), [
                'kind' => 'mix',
                'name' => 'Faqat non',
                'measurement_unit_id' => $this->qopId,
                'outputs' => [['bread_category_id' => $this->non->id, 'quantity' => 80]],
                'ingredients' => [['ingredient_id' => $this->un->id, 'quantity' => 10]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('outputs');
    }

    public function test_mix_outputs_can_be_updated_and_legacy_output_quantity_is_ignored(): void
    {
        $recipe = $this->createMultiRecipe();

        $this->actingAs($this->owner)
            ->putJson($this->url("recipes/{$recipe['id']}"), [
                'outputs' => [
                    ['bread_category_id' => $this->non->id, 'quantity' => 70],
                    ['bread_category_id' => $this->somsa->id, 'quantity' => 30],
                    ['bread_category_id' => $this->patir->id, 'quantity' => 20],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(3, 'data.recipe.outputs')
            ->assertJsonPath('data.recipe.output_quantity', 120);

        // Eski ilova faqat output_quantity yuboradi — taqsimot saqlanadi.
        $this->actingAs($this->owner)
            ->putJson($this->url("recipes/{$recipe['id']}"), ['output_quantity' => 999])
            ->assertOk()
            ->assertJsonPath('data.recipe.output_quantity', 120)
            ->assertJsonCount(3, 'data.recipe.outputs');
    }

    public function test_production_with_items_splits_cost_by_sales_value(): void
    {
        $recipe = $this->createMultiRecipe();

        $res = $this->actingAs($this->owner)
            ->postJson($this->url('productions'), [
                'recipe_id' => $recipe['id'],
                'date' => '2026-10-31',
                'batch_count' => 2,
                'items' => [
                    ['bread_category_id' => $this->non->id, 'quantity' => 150],
                    ['bread_category_id' => $this->somsa->id, 'quantity' => 75],
                    ['bread_category_id' => $this->patir->id, 'quantity' => 0],
                ],
            ])
            ->assertCreated()
            ->json('data.productions');

        $this->assertCount(2, $res);
        $this->assertNotNull($res[0]['batch_id']);
        $this->assertSame($res[0]['batch_id'], $res[1]['batch_id']);

        $rows = Production::query()->orderBy('bread_produced', 'desc')->get();
        // Qiymat: non 150×4000 = 600 000, somsa 75×8000 = 600 000 → teng ulush.
        $this->assertSame(150, $rows[0]->bread_produced);
        $this->assertSame(300000.0, (float) $rows[0]->ingredient_cost);
        $this->assertSame(300000.0, (float) $rows[1]->ingredient_cost);
        $this->assertSame(1.0, (float) $rows[0]->batch_count);
        $this->assertSame(2.0, (float) $rows->sum('batch_count'));
        $this->assertSame(600000.0, (float) $rows->sum('ingredient_cost'));
        $this->assertSame(100.0, (float) $rows->sum('flour_used_kg'));
    }

    public function test_uneven_split_still_sums_to_the_cent(): void
    {
        $recipe = $this->createMultiRecipe();

        $this->actingAs($this->owner)
            ->postJson($this->url('productions'), [
                'recipe_id' => $recipe['id'],
                'date' => '2026-10-31',
                'batch_count' => 1,
                'items' => [
                    ['bread_category_id' => $this->non->id, 'quantity' => 33],
                    ['bread_category_id' => $this->somsa->id, 'quantity' => 17],
                    ['bread_category_id' => $this->patir->id, 'quantity' => 11],
                ],
            ])
            ->assertCreated();

        $rows = Production::all();
        $this->assertCount(3, $rows);
        $this->assertSame(300000.0, round((float) $rows->sum('ingredient_cost'), 2));
        $this->assertSame(1.0, round((float) $rows->sum('batch_count'), 4));
    }

    public function test_legacy_client_on_multi_recipe_uses_default_split(): void
    {
        $recipe = $this->createMultiRecipe();

        // Eski ilova: faqat bread_category_id.
        $this->actingAs($this->owner)
            ->postJson($this->url('productions'), [
                'recipe_id' => $recipe['id'],
                'bread_category_id' => $this->non->id,
                'date' => '2026-10-31',
                'batch_count' => 1.5,
            ])
            ->assertCreated();

        $rows = Production::query()->get()->keyBy('bread_category_id');
        $this->assertSame(120, $rows[$this->non->id]->bread_produced);
        $this->assertSame(60, $rows[$this->somsa->id]->bread_produced);
    }

    public function test_daily_report_cash_and_returns_follow_the_split(): void
    {
        $recipe = $this->createMultiRecipe();

        $this->actingAs($this->owner)
            ->postJson($this->url('productions'), [
                'recipe_id' => $recipe['id'],
                'date' => '2026-10-31',
                'batch_count' => 1,
                'items' => [
                    ['bread_category_id' => $this->non->id, 'quantity' => 80],
                    ['bread_category_id' => $this->somsa->id, 'quantity' => 40],
                ],
            ])
            ->assertCreated();

        $somsaRow = Production::query()->where('bread_category_id', $this->somsa->id)->firstOrFail();

        // Somsa uchun vozvrat shu partiyadagi somsa qatoriga bog'lanadi.
        $this->actingAs($this->owner)
            ->postJson($this->url('returns'), [
                'production_id' => $somsaRow->id,
                'bread_category_id' => $this->somsa->id,
                'date' => '2026-10-31',
                'quantity' => 5,
                'price_per_unit' => 8000,
            ])
            ->assertCreated();

        $report = $this->actingAs($this->owner)
            ->getJson($this->url('reports/daily?date=2026-10-31'))
            ->assertOk()
            ->json('data.report');

        // Tushum: 80×4000 + 40×8000 = 640 000; vozvrat 40 000; xarajat 300 000.
        $this->assertSame(1, $report['production']['count']);
        $this->assertSame(120, $report['production']['total_bread']);
        $this->assertSame(640000.0, (float) $report['sales']['gross_amount']);
        $this->assertSame(300000.0, (float) $report['production']['ingredient_cost']);
        $this->assertSame(300000.0, (float) $report['profit']);

        // Kassa: har mahsulot qatori o'z daromad/xarajat yozuvi bilan.
        $income = (float) \DB::table('cash_transactions')
            ->where('shop_id', $this->shop->id)
            ->where('category', 'production_income')
            ->sum('amount');
        $cost = (float) \DB::table('cash_transactions')
            ->where('shop_id', $this->shop->id)
            ->where('category', 'production_cost')
            ->sum('amount');
        $this->assertSame(640000.0, $income);
        $this->assertSame(300000.0, $cost);

        // Ro'yxatda partiya jamisi har qatorda.
        $list = $this->actingAs($this->owner)
            ->getJson($this->url('productions?date=2026-10-31'))
            ->assertOk()
            ->json('data.productions');
        $this->assertCount(2, $list);
        $this->assertSame(1.0, (float) $list[0]['batch_total_count']);
    }

    public function test_update_batch_items_in_place_and_block_removing_returned_item(): void
    {
        $recipe = $this->createMultiRecipe();

        $this->actingAs($this->owner)
            ->postJson($this->url('productions'), [
                'recipe_id' => $recipe['id'],
                'date' => '2026-10-31',
                'batch_count' => 1,
                'items' => [
                    ['bread_category_id' => $this->non->id, 'quantity' => 80],
                    ['bread_category_id' => $this->somsa->id, 'quantity' => 40],
                ],
            ])
            ->assertCreated();

        $nonRow = Production::query()->where('bread_category_id', $this->non->id)->firstOrFail();
        $somsaRow = Production::query()->where('bread_category_id', $this->somsa->id)->firstOrFail();

        // 2 qop, non 170, somsa olib tashlanadi, patir qo'shiladi.
        $res = $this->actingAs($this->owner)
            ->putJson($this->url("productions/{$nonRow->id}"), [
                'batch_count' => 2,
                'items' => [
                    ['bread_category_id' => $this->non->id, 'quantity' => 170],
                    ['bread_category_id' => $this->patir->id, 'quantity' => 30],
                ],
            ])
            ->assertOk()
            ->json('data.productions');

        $this->assertCount(2, $res);
        $this->assertDatabaseMissing('productions', ['id' => $somsaRow->id]);
        $this->assertSame(170, $nonRow->fresh()->bread_produced);
        $this->assertSame($nonRow->batch_id, $nonRow->fresh()->batch_id);
        $this->assertSame(600000.0, round((float) Production::query()->sum('ingredient_cost'), 2));

        // Patirga vozvrat bo'lsa — uni partiyadan olib tashlab bo'lmaydi.
        $patirRow = Production::query()->where('bread_category_id', $this->patir->id)->firstOrFail();
        BreadReturn::create([
            'shop_id' => $this->shop->id,
            'production_id' => $patirRow->id,
            'bread_category_id' => $this->patir->id,
            'date' => '2026-10-31',
            'quantity' => 1,
            'price_per_unit' => 6000,
            'total_amount' => 6000,
            'created_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->putJson($this->url("productions/{$nonRow->id}"), [
                'batch_count' => 2,
                'items' => [['bread_category_id' => $this->non->id, 'quantity' => 170]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_delete_whole_batch(): void
    {
        $recipe = $this->createMultiRecipe();

        $this->actingAs($this->owner)
            ->postJson($this->url('productions'), [
                'recipe_id' => $recipe['id'],
                'bread_category_id' => $this->non->id,
                'date' => '2026-10-31',
                'batch_count' => 1,
            ])
            ->assertCreated();

        $this->assertSame(2, Production::query()->count());
        $row = Production::query()->firstOrFail();
        $this->actingAs($this->owner)
            ->deleteJson($this->url("productions/{$row->id}?whole_batch=1"))
            ->assertOk();

        $this->assertSame(0, Production::query()->count());
        $this->assertSame(0, \DB::table('cash_transactions')->where('shop_id', $this->shop->id)->count());
    }

    public function test_single_output_recipe_behaves_as_before(): void
    {
        $recipe = Recipe::create([
            'shop_id' => $this->shop->id,
            'bread_category_id' => $this->patir->id,
            'measurement_unit_id' => $this->qopId,
            'name' => 'Patir',
            'output_quantity' => 60,
            'is_active' => true,
        ]);
        $recipe->outputs()->create(['bread_category_id' => $this->patir->id, 'quantity' => 60]);
        $recipe->recipeIngredients()->create(['ingredient_id' => $this->un->id, 'quantity' => 50]);

        $this->actingAs($this->owner)
            ->postJson($this->url('productions'), [
                'recipe_id' => $recipe->id,
                'bread_category_id' => $this->patir->id,
                'date' => '2026-10-31',
                'batch_count' => 1.5,
            ])
            ->assertCreated()
            ->assertJsonPath('data.production.batch_id', null)
            ->assertJsonPath('data.production.bread_produced', 90);

        $row = Production::query()->firstOrFail();
        $this->assertSame(450000.0, (float) $row->ingredient_cost);

        $this->actingAs($this->owner)
            ->putJson($this->url("productions/{$row->id}"), ['batch_count' => 2])
            ->assertOk()
            ->assertJsonPath('data.production.bread_produced', 120);
    }
}
