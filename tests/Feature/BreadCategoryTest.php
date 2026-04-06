<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Models\BreadCategory;
use App\Models\Currency;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BreadCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Shop $shop;
    private string $uzsId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uzsId = Currency::query()->where('code', 'UZS')->value('id');
        $this->user = User::factory()->create();
        $this->shop = Shop::create([
            'name' => 'Test',
            'slug' => 'test-' . Str::random(5),
            'is_active' => true,
            'currency_id' => $this->uzsId,
        ]);

        $this->user->shops()->attach($this->shop->id, [
            'user_type' => ShopUserType::Owner,
        ]);
    }

    public function test_can_list_bread_categories(): void
    {
        BreadCategory::create([
            'shop_id' => $this->shop->id,
            'name' => 'Oq non',
            'selling_price' => 4000,
            'currency_id' => $this->uzsId,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/shops/{$this->shop->id}/bread-categories");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(1, 'data.bread_categories');
    }

    public function test_can_create_bread_category(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/shops/{$this->shop->id}/bread-categories", [
                'name' => 'Patir',
                'selling_price' => 6000,
                'currency_id' => $this->uzsId,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.bread_category.name', 'Patir')
            ->assertJsonPath('data.bread_category.currency_id', $this->uzsId);
    }

    public function test_create_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/shops/{$this->shop->id}/bread-categories", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'selling_price']);
    }

    public function test_can_update_bread_category(): void
    {
        $category = BreadCategory::create([
            'shop_id' => $this->shop->id,
            'name' => 'Oq non',
            'selling_price' => 4000,
            'currency_id' => $this->uzsId,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/shops/{$this->shop->id}/bread-categories/{$category->id}", [
                'selling_price' => 5000,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.bread_category.selling_price', '5000.00');
    }

    public function test_can_delete_bread_category(): void
    {
        $category = BreadCategory::create([
            'shop_id' => $this->shop->id,
            'name' => 'Test',
            'selling_price' => 1000,
            'currency_id' => $this->uzsId,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/shops/{$this->shop->id}/bread-categories/{$category->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_unauthorized_user_cannot_access(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->getJson("/api/v1/shops/{$this->shop->id}/bread-categories");

        $response->assertStatus(403);
    }
}
