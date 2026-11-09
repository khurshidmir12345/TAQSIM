<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Models\BreadCategory;
use App\Models\CashTransaction;
use App\Models\Currency;
use App\Models\Production;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\MeasurementUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Retseptsiz chiqim: do'kon sozlamasi va retseptsiz mahsulot chiqimi.
 */
class ProductionWithoutRecipeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Shop $shop;
    private BreadCategory $lepyoshka;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MeasurementUnitSeeder::class);

        $uzs = Currency::query()->where('code', 'UZS')->value('id');
        $this->owner = User::factory()->create();
        $this->shop = Shop::create([
            'name' => 'Nonvoyxona',
            'slug' => 'shop-' . Str::random(6),
            'is_active' => true,
            'currency_id' => $uzs,
            'cash_track_production' => true,
        ]);
        $this->owner->shops()->attach($this->shop->id, ['user_type' => ShopUserType::Owner]);

        $this->lepyoshka = BreadCategory::create([
            'shop_id' => $this->shop->id,
            'name' => 'Lepyoshka',
            'selling_price' => 3000,
            'currency_id' => $uzs,
        ]);
    }

    private function url(string $path): string
    {
        return "/api/v1/shops/{$this->shop->id}/{$path}";
    }

    public function test_owner_toggles_setting_and_shop_exposes_it(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/v1/shops')
            ->assertOk()
            ->assertJsonPath('data.shops.0.production_without_recipe', false);

        $this->actingAs($this->owner)
            ->putJson($this->url('production-settings'), ['without_recipe' => true])
            ->assertOk()
            ->assertJsonPath('data.without_recipe', true);

        $this->assertTrue($this->shop->fresh()->production_without_recipe);
    }

    public function test_seller_cannot_change_setting(): void
    {
        $seller = User::factory()->create();
        $seller->shops()->attach($this->shop->id, [
            'user_type' => ShopUserType::Seller,
            'permissions' => ['manage_production'],
        ]);

        $this->actingAs($seller)
            ->putJson($this->url('production-settings'), ['without_recipe' => true])
            ->assertForbidden();
    }

    public function test_production_without_recipe_is_created_and_edited(): void
    {
        $res = $this->actingAs($this->owner)
            ->postJson($this->url('productions'), [
                'date' => now()->toDateString(),
                'items' => [['bread_category_id' => $this->lepyoshka->id, 'quantity' => 150]],
            ])
            ->assertCreated()
            ->json('data.production');

        $this->assertSame('', $res['recipe_id']);
        $this->assertNull($res['recipe']);
        $this->assertSame(150, $res['bread_produced']);

        $p = Production::findOrFail($res['id']);
        $this->assertNull($p->recipe_id);
        $this->assertNull($p->batch_id);
        $this->assertEquals(0, (float) $p->ingredient_cost);

        // Kassada faqat tushum, xom ashyo chiqimi yo'q.
        $this->assertSame(1, CashTransaction::query()->where('source_id', $p->id)->count());

        // Ro'yxatda ham to'g'ri ko'rinadi.
        $this->actingAs($this->owner)
            ->getJson($this->url('productions?date=' . now()->toDateString()))
            ->assertOk()
            ->assertJsonPath('data.productions.0.gross_revenue', 450000);

        $this->actingAs($this->owner)
            ->putJson($this->url("productions/{$p->id}"), [
                'items' => [['bread_category_id' => $this->lepyoshka->id, 'quantity' => 120]],
            ])
            ->assertOk()
            ->assertJsonPath('data.production.bread_produced', 120);

        // Statistika retseptsiz qatorni ham hisoblaydi.
        $this->actingAs($this->owner)
            ->getJson($this->url('reports/daily?date=' . now()->toDateString()))
            ->assertOk();
    }

    public function test_recipe_production_still_requires_batch_count(): void
    {
        $this->actingAs($this->owner)
            ->postJson($this->url('productions'), [
                'date' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }
}
