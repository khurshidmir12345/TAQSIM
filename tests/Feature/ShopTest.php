<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Models\BusinessType;
use App\Models\Currency;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BusinessTypeSeeder::class);
        $this->seed(CurrencySeeder::class);

        $this->user = User::factory()->create();

        $uzsId = Currency::query()->where('code', 'UZS')->value('id');

        $this->shop = Shop::create([
            'name' => 'Test Nonvoyxona',
            'slug' => 'test-nonvoyxona-' . Str::random(5),
            'is_active' => true,
            'currency_id' => $uzsId,
        ]);

        $this->user->shops()->attach($this->shop->id, [
            'user_type' => ShopUserType::Owner,
        ]);
    }

    public function test_user_can_list_shops(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/shops');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data.shops');
    }

    public function test_user_can_create_shop(): void
    {
        $businessTypeId = BusinessType::query()->value('id');
        $currencyId = Currency::query()->where('code', 'UZS')->value('id');

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/shops', [
                'business_type_id' => $businessTypeId,
                'currency_id' => $currencyId,
                'name' => 'Yangi Nonvoyxona',
                'address' => 'Toshkent',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.shop.name', 'Yangi Nonvoyxona');
    }

    public function test_user_can_view_own_shop(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/shops/{$this->shop->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_user_cannot_view_others_shop(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->getJson("/api/v1/shops/{$this->shop->id}");

        $response->assertStatus(403);
    }

    public function test_owner_can_update_shop(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/shops/{$this->shop->id}", [
                'name' => 'Yangilangan nom',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.shop.name', 'Yangilangan nom');
    }

    public function test_owner_can_delete_shop(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/shops/{$this->shop->id}");

        $response->assertOk();
        $this->assertSoftDeleted('shops', ['id' => $this->shop->id]);
    }

    public function test_seller_cannot_delete_shop(): void
    {
        $seller = User::factory()->create();
        $seller->shops()->attach($this->shop->id, [
            'user_type' => ShopUserType::Seller,
        ]);

        $response = $this->actingAs($seller)
            ->deleteJson("/api/v1/shops/{$this->shop->id}");

        $response->assertStatus(403);
    }
}
