<?php

namespace Tests\Feature;

use App\Enums\ShopPermission;
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

/**
 * Muddat tugagach qaysi bo'limlar yopiladi.
 *
 * Ikki qoida sinaladi:
 *  1. Hisob egaga bog'lanadi — xodim egasining muddati ichida ishlaydi.
 *  2. Bepul bo'limlar (bosh sahifa, ishlab chiqarish, kassa, retsept)
 *     muddat tugagach ham ochiq qoladi.
 */
class AccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Shop $shop;
    private string $uzsId;
    private string $businessTypeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BusinessTypeSeeder::class);
        $this->seed(CurrencySeeder::class);

        config()->set('access.enabled', true);

        $this->uzsId = Currency::query()->where('code', 'UZS')->value('id');
        $this->businessTypeId = BusinessType::query()->value('id');
        $this->owner = User::factory()->create();
        $this->shop = $this->makeShop($this->owner);
    }

    private function makeShop(User $owner, string $name = 'Test Shop'): Shop
    {
        $shop = Shop::create([
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(5),
            'is_active' => true,
            'currency_id' => $this->uzsId,
        ]);

        $owner->shops()->attach($shop->id, ['user_type' => ShopUserType::Owner]);

        return $shop;
    }

    private function expireOwner(): void
    {
        $this->owner->forceFill(['access_until' => now()->subDay()])->save();
    }

    // ── Muddat ichida ─────────────────────────────────────────────────────

    public function test_new_user_gets_a_trial_period(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->access_until);
        $this->assertSame('trial', $user->access_source);
        $this->assertTrue($user->hasFullAccess());
        $this->assertSame('trial', $user->accessStatus());
    }

    public function test_paid_sections_are_open_while_the_period_lasts(): void
    {
        $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/reports/statistics")
            ->assertOk();

        $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/customer-orders")
            ->assertOk();

        $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/employees")
            ->assertOk();
    }

    // ── Muddat tugagach ───────────────────────────────────────────────────

    public function test_statistics_is_closed_when_the_period_ends(): void
    {
        $this->expireOwner();

        $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/reports/statistics")
            ->assertForbidden()
            ->assertJson(['success' => false, 'code' => 'feature_unavailable']);
    }

    public function test_orders_and_customers_are_closed_when_the_period_ends(): void
    {
        $this->expireOwner();

        $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/customer-orders")
            ->assertForbidden()
            ->assertJson(['code' => 'feature_unavailable']);

        $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/customers")
            ->assertForbidden()
            ->assertJson(['code' => 'feature_unavailable']);
    }

    public function test_employees_is_closed_when_the_period_ends(): void
    {
        $this->expireOwner();

        $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/employees")
            ->assertForbidden()
            ->assertJson(['code' => 'feature_unavailable']);
    }

    /**
     * Bepul bo'limlar tegilmasligi kerak — aks holda muddat tugagan
     * foydalanuvchi ilovadan umuman foydalana olmay qoladi.
     */
    public function test_free_sections_stay_open_when_the_period_ends(): void
    {
        $this->expireOwner();

        $paths = [
            'reports/daily?date=' . now()->toDateString(),
            'reports/summary',
            'bread-categories',
            'ingredients',
            'recipes',
            'productions',
            'returns',
            'cash',
            'expenses',
        ];

        foreach ($paths as $path) {
            $this->actingAs($this->owner)
                ->getJson("/api/v1/shops/{$this->shop->id}/{$path}")
                ->assertOk("`{$path}` bepul bo'lishi kerak edi");
        }
    }

    // ── Xodim (seller) egasining muddatiga bog'liq ────────────────────────

    public function test_seller_is_closed_when_the_owner_period_ends(): void
    {
        $seller = User::factory()->create();
        $seller->shops()->attach($this->shop->id, [
            'user_type' => ShopUserType::Seller,
            'permissions' => [
                ShopPermission::ManageOrders->value,
                ShopPermission::ViewReports->value,
            ],
        ]);

        // Egasi muddat ichida — xodim ham ishlaydi.
        $this->actingAs($seller)
            ->getJson("/api/v1/shops/{$this->shop->id}/customer-orders")
            ->assertOk();

        $this->expireOwner();

        // Xodimning o'z muddati hali tugamagan, lekin egasiniki tugagan.
        $this->assertTrue($seller->fresh()->hasFullAccess());

        $this->actingAs($seller)
            ->getJson("/api/v1/shops/{$this->shop->id}/customer-orders")
            ->assertForbidden()
            ->assertJson(['code' => 'feature_unavailable']);
    }

    // ── Biznes soni ───────────────────────────────────────────────────────

    public function test_second_business_is_blocked_when_the_period_ends(): void
    {
        $this->expireOwner();

        $this->actingAs($this->owner)
            ->postJson('/api/v1/shops', [
                'business_type_id' => $this->businessTypeId,
                'currency_id' => $this->uzsId,
                'name' => 'Ikkinchi biznes',
            ])
            ->assertForbidden()
            ->assertJson(['code' => 'feature_unavailable']);
    }

    public function test_first_business_is_always_allowed(): void
    {
        $fresh = User::factory()->create();
        $fresh->forceFill(['access_until' => now()->subDay()])->save();

        $this->actingAs($fresh)
            ->postJson('/api/v1/shops', [
                'business_type_id' => $this->businessTypeId,
                'currency_id' => $this->uzsId,
                'name' => 'Birinchi biznes',
            ])
            ->assertCreated();
    }

    // ── Javobdagi `features` ro'yxati ─────────────────────────────────────

    public function test_shop_response_lists_open_features(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/v1/shops')
            ->assertOk()
            ->assertJsonCount(4, 'data.shops.0.features')
            ->assertJsonPath('data.shops.0.features', [
                'reports', 'orders', 'employees', 'multi_shop',
            ]);

        $this->expireOwner();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/shops')
            ->assertOk()
            ->assertJsonPath('data.shops.0.features', []);
    }

    public function test_seller_sees_the_owner_features(): void
    {
        $seller = User::factory()->create();
        $seller->shops()->attach($this->shop->id, [
            'user_type' => ShopUserType::Seller,
            'permissions' => [ShopPermission::ManageOrders->value],
        ]);

        $this->expireOwner();

        $this->actingAs($seller)
            ->getJson('/api/v1/shops')
            ->assertOk()
            ->assertJsonPath('data.shops.0.features', []);
    }

    // ── Deploysiz to'xtatish tugmasi ──────────────────────────────────────

    public function test_disabled_flag_opens_everything_again(): void
    {
        $this->expireOwner();
        config()->set('access.enabled', false);

        $this->actingAs($this->owner)
            ->getJson("/api/v1/shops/{$this->shop->id}/reports/statistics")
            ->assertOk();

        $this->actingAs($this->owner)
            ->getJson('/api/v1/shops')
            ->assertJsonPath('data.shops.0.features', [
                'reports', 'orders', 'employees', 'multi_shop',
            ]);
    }

    // ── Holat nomlari (admin paneli uchun) ────────────────────────────────

    public function test_access_status_reflects_the_source_and_the_date(): void
    {
        $user = User::factory()->create();
        $this->assertSame('trial', $user->accessStatus());

        $user->forceFill(['access_source' => 'paid'])->save();
        $this->assertSame('paid', $user->fresh()->accessStatus());

        $user->forceFill(['access_until' => now()->subDay()])->save();
        $this->assertSame('expired', $user->fresh()->accessStatus());
    }
}
