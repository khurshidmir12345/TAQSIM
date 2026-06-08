<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Currency;
use App\Models\Shop;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ExchangeRateSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BusinessTypeSeeder::class);
        $this->seed(CurrencySeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(ExchangeRateSeeder::class);
    }

    private function uzsShopFor(User $user): Shop
    {
        $shop = Shop::create([
            'name' => 'Test '.Str::random(4),
            'slug' => 'test-'.Str::random(6),
            'is_active' => true,
            'currency_id' => Currency::query()->where('code', 'UZS')->value('id'),
        ]);

        $user->shops()->attach($shop->id, ['user_type' => \App\Enums\ShopUserType::Owner]);

        return $shop;
    }

    public function test_owner_gets_trial_on_first_subscription_access(): void
    {
        // Trial endi user yaratilganda emas, owner sub endpointiga murojaat
        // qilganda (yoki birinchi do'kon ochilganda) lazily yaratiladi.
        $user = User::factory()->create();

        $this->assertNull($user->currentSubscription()->first());

        $this->actingAs($user)->getJson('/api/v1/subscription/me')->assertOk();

        $subscription = $user->currentSubscription()->with('plan')->first();

        $this->assertNotNull($subscription);
        $this->assertTrue($subscription->plan->is_trial);
        $this->assertSame('trialing', $subscription->effectiveStatus()->value);
    }

    public function test_seller_never_gets_trial(): void
    {
        $owner = User::factory()->create();
        $shop = $this->uzsShopFor($owner);

        $seller = User::factory()->create();
        $seller->shops()->attach($shop->id, ['user_type' => \App\Enums\ShopUserType::Seller]);

        // Hatto obuna xizmati chaqirilsa ham — sellerga trial berilmaydi.
        $this->assertNull(app(SubscriptionService::class)->ensureTrial($seller));
        $this->assertNull($seller->currentSubscription()->first());
        $this->assertSame('seller', $seller->globalUserType());
    }

    public function test_plans_endpoint_returns_paid_plans_with_local_price(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/subscription/plans');

        $response->assertOk()
            ->assertJson(['success' => true]);

        $codes = collect($response->json('data.plans'))->pluck('code');
        $this->assertTrue($codes->contains('light'));
        $this->assertFalse($codes->contains('trial'));

        $light = collect($response->json('data.plans'))->firstWhere('code', 'light');
        $this->assertEquals(31500, $light['price_local']); // 2.5 * 12600 → 31500
    }

    public function test_user_can_purchase_plan_with_sufficient_balance(): void
    {
        $user = User::factory()->create(['balance' => 50000]);
        $light = SubscriptionPlan::where('code', 'light')->first();

        $response = $this->actingAs($user)->postJson('/api/v1/subscription/purchase', [
            'plan_id' => $light->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.subscription.status', 'active');

        $this->assertEquals(18500, (float) $user->fresh()->balance); // 50000 - 31500
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'type' => 'subscription_charge',
        ]);
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'type' => 'subscription',
            'status' => 'paid',
        ]);
    }

    public function test_purchase_fails_with_insufficient_balance(): void
    {
        $user = User::factory()->create(['balance' => 1000]);
        $premium = SubscriptionPlan::where('code', 'premium')->first();

        $response = $this->actingAs($user)->postJson('/api/v1/subscription/purchase', [
            'plan_id' => $premium->id,
        ]);

        $response->assertStatus(402)
            ->assertJsonPath('code', 'insufficient_balance');
    }

    public function test_expired_subscription_blocks_feature_routes(): void
    {
        $user = User::factory()->create();
        app(SubscriptionService::class)->ensureTrial($user);

        $user->subscriptions()->update([
            'ends_at' => now()->subDays(10),
            'trial_ends_at' => now()->subDays(10),
            'grace_ends_at' => now()->subDays(7),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/shops');

        $response->assertStatus(402)
            ->assertJsonPath('code', 'subscription_required');
    }

    public function test_grace_period_allows_read_but_blocks_write(): void
    {
        $user = User::factory()->create();
        app(SubscriptionService::class)->ensureTrial($user);
        $businessTypeId = BusinessType::query()->value('id');

        $user->subscriptions()->update([
            'ends_at' => now()->subDay(),
            'trial_ends_at' => now()->subDay(),
            'grace_ends_at' => now()->addDays(2),
        ]);

        $this->actingAs($user)->getJson('/api/v1/shops')->assertOk();

        $this->actingAs($user)->postJson('/api/v1/shops', [
            'business_type_id' => $businessTypeId,
            'name' => 'Yangi',
        ])->assertStatus(402)->assertJsonPath('code', 'subscription_grace_readonly');
    }

    public function test_plan_limit_blocks_extra_shop(): void
    {
        $user = User::factory()->create();
        $light = SubscriptionPlan::where('code', 'light')->first(); // max_shops = 1
        app(SubscriptionService::class)->activatePlan($user, $light);

        $businessTypeId = BusinessType::query()->value('id');
        $currencyId = Currency::query()->where('code', 'UZS')->value('id');

        // 1-do'kon — ruxsat
        $this->actingAs($user)->postJson('/api/v1/shops', [
            'business_type_id' => $businessTypeId,
            'currency_id' => $currencyId,
            'name' => 'Birinchi',
        ])->assertStatus(201);

        // 2-do'kon — limit
        $this->actingAs($user)->postJson('/api/v1/shops', [
            'business_type_id' => $businessTypeId,
            'currency_id' => $currencyId,
            'name' => 'Ikkinchi',
        ])->assertStatus(403)->assertJsonPath('code', 'plan_limit_reached');
    }
}
