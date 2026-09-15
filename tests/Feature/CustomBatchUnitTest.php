<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Models\BreadCategory;
use App\Models\Currency;
use App\Models\Ingredient;
use App\Models\MeasurementUnit;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomBatchUnitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Shop $shop;
    private Shop $otherShop;
    private string $uzsId;
    private MeasurementUnit $qop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uzsId = Currency::query()->where('code', 'UZS')->value('id');
        $this->user = User::factory()->create();
        $this->shop = $this->makeShop();
        $this->otherShop = $this->makeShop();

        $this->user->shops()->attach($this->shop->id, ['user_type' => ShopUserType::Owner]);
        $this->user->shops()->attach($this->otherShop->id, ['user_type' => ShopUserType::Owner]);

        $this->qop = MeasurementUnit::firstOrCreate(['code' => 'qop'], [
            'type' => 'batch',
            'name_uz' => 'Qop', 'name_uz_cyrl' => 'Қоп', 'name_ru' => 'Мешок',
            'name_kk' => 'Қап', 'name_ky' => 'Кап', 'name_tr' => 'Çuval',
            'icon' => '🧺', 'sort_order' => 1, 'is_active' => true,
        ]);
    }

    private function makeShop(): Shop
    {
        return Shop::create([
            'name' => 'Test',
            'slug' => 'test-' . Str::random(5),
            'is_active' => true,
            'currency_id' => $this->uzsId,
        ]);
    }

    private function unitsUrl(Shop $shop): string
    {
        return "/api/v1/shops/{$shop->id}/measurement-units";
    }

    public function test_shop_can_create_custom_batch_unit_visible_only_to_itself(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson($this->unitsUrl($this->shop), ['name' => 'Laganda', 'icon' => '🍽️']);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Laganda')
            ->assertJsonPath('data.icon', '🍽️')
            ->assertJsonPath('data.type', 'batch')
            ->assertJsonPath('data.is_custom', true);

        $id = $response->json('data.id');

        // O'z do'konida ko'rinadi (tizim birligi bilan birga).
        $own = $this->actingAs($this->user)->getJson($this->unitsUrl($this->shop) . '/batch');
        $own->assertOk();
        $ids = collect($own->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($id));
        $this->assertTrue($ids->contains($this->qop->id));

        // Boshqa do'konda ko'rinmaydi.
        $other = $this->actingAs($this->user)->getJson($this->unitsUrl($this->otherShop) . '/batch');
        $other->assertOk();
        $this->assertFalse(collect($other->json('data'))->pluck('id')->contains($id));

        // Ommaviy endpointda ham ko'rinmaydi.
        $public = $this->getJson('/api/v1/measurement-units/batch');
        $public->assertOk();
        $this->assertFalse(collect($public->json('data'))->pluck('id')->contains($id));
    }

    public function test_duplicate_name_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->postJson($this->unitsUrl($this->shop), ['name' => 'Tandir', 'icon' => '🔥'])
            ->assertCreated();

        $this->actingAs($this->user)
            ->postJson($this->unitsUrl($this->shop), ['name' => 'tandir', 'icon' => '🔥'])
            ->assertStatus(422);

        // Tizim birligi nomi ham band.
        $this->actingAs($this->user)
            ->postJson($this->unitsUrl($this->shop), ['name' => 'Qop', 'icon' => '🧺'])
            ->assertStatus(422);
    }

    public function test_recipe_accepts_own_custom_unit_but_not_another_shops(): void
    {
        $unitId = $this->actingAs($this->user)
            ->postJson($this->unitsUrl($this->otherShop), ['name' => 'Tandir', 'icon' => '🔥'])
            ->json('data.id');

        $payload = fn (Shop $shop) => [
            'bread_category_id' => BreadCategory::create([
                'shop_id' => $shop->id,
                'name' => 'Non',
                'selling_price' => 4000,
                'currency_id' => $this->uzsId,
            ])->id,
            'measurement_unit_id' => $unitId,
            'name' => 'Non',
            'output_quantity' => 50,
            'ingredients' => [[
                'ingredient_id' => Ingredient::create([
                    'shop_id' => $shop->id,
                    'name' => 'Un',
                    'unit' => 'kg',
                    'price_per_unit' => 5000,
                    'currency_id' => $this->uzsId,
                ])->id,
                'quantity' => 25,
            ]],
        ];

        $this->actingAs($this->user)
            ->postJson("/api/v1/shops/{$this->otherShop->id}/recipes", $payload($this->otherShop))
            ->assertCreated();

        $this->actingAs($this->user)
            ->postJson("/api/v1/shops/{$this->shop->id}/recipes", $payload($this->shop))
            ->assertStatus(422);
    }

    public function test_custom_unit_in_use_cannot_be_deleted(): void
    {
        $unitId = $this->actingAs($this->user)
            ->postJson($this->unitsUrl($this->shop), ['name' => 'Tandir', 'icon' => '🔥'])
            ->json('data.id');

        $this->shop->recipes()->create([
            'name' => 'Non',
            'bread_category_id' => BreadCategory::create([
                'shop_id' => $this->shop->id,
                'name' => 'Non',
                'selling_price' => 4000,
                'currency_id' => $this->uzsId,
            ])->id,
            'measurement_unit_id' => $unitId,
            'output_quantity' => 10,
        ]);

        $this->actingAs($this->user)
            ->deleteJson($this->unitsUrl($this->shop) . '/' . $unitId)
            ->assertStatus(422);

        // Tizim birligini o'chirib bo'lmaydi (topilmaydi).
        $this->actingAs($this->user)
            ->deleteJson($this->unitsUrl($this->shop) . '/' . $this->qop->id)
            ->assertNotFound();
    }

    public function test_unused_custom_unit_can_be_deleted(): void
    {
        $unitId = $this->actingAs($this->user)
            ->postJson($this->unitsUrl($this->shop), ['name' => 'Tandir', 'icon' => '🔥'])
            ->json('data.id');

        $this->actingAs($this->user)
            ->deleteJson($this->unitsUrl($this->shop) . '/' . $unitId)
            ->assertOk();

        $this->assertDatabaseMissing('measurement_units', ['id' => $unitId]);
    }
}
