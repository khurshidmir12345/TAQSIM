<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Models\Currency;
use App\Models\PhoneVerificationCode;
use App\Models\Shop;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\EmployeeService;
use App\Services\SubscriptionService;
use Database\Seeders\AppSettingSeeder;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ExchangeRateSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BusinessTypeSeeder::class);
        $this->seed(CurrencySeeder::class);
        $this->seed(SubscriptionPlanSeeder::class);
        $this->seed(ExchangeRateSeeder::class);
        $this->seed(AppSettingSeeder::class);
    }

    private function ownerWithShop(?string $planCode = null, float $balance = 0): array
    {
        $owner = User::factory()->create(['balance' => $balance]);

        if ($planCode) {
            $plan = SubscriptionPlan::where('code', $planCode)->first();
            app(SubscriptionService::class)->activatePlan($owner, $plan);
        }

        $shop = Shop::create([
            'name' => 'Test '.Str::random(4),
            'slug' => 'test-'.Str::random(6),
            'is_active' => true,
            'currency_id' => Currency::query()->where('code', 'UZS')->value('id'),
        ]);

        $owner->shops()->attach($shop->id, ['user_type' => ShopUserType::Owner]);

        return [$owner, $shop];
    }

    private function latestCode(string $phone): string
    {
        return PhoneVerificationCode::where('phone', $phone)->latest()->first()->code;
    }

    public function test_owner_creates_free_employee_with_code(): void
    {
        // Trial = cheksiz xodim → bepul
        [$owner, $shop] = $this->ownerWithShop();
        $phone = '+998900000001';

        $store = $this->actingAs($owner)->postJson("/api/v1/shops/{$shop->id}/employees", [
            'name' => 'Ali',
            'phone' => $phone,
            'password' => 'secret123',
        ]);

        $store->assertOk()
            ->assertJsonPath('data.requires_code', true)
            ->assertJsonPath('data.is_paid', false);

        $confirm = $this->actingAs($owner)->postJson("/api/v1/shops/{$shop->id}/employees/confirm", [
            'phone' => $phone,
            'code' => $this->latestCode($phone),
        ]);

        $confirm->assertStatus(201)
            ->assertJsonPath('data.employee.is_paid_seat', false);

        $this->assertDatabaseHas('user_shops', [
            'shop_id' => $shop->id,
            'user_type' => 'seller',
        ]);

        $this->assertDatabaseHas('seller_subs', [
            'shop_id' => $shop->id,
            'is_paid_seat' => false,
            'status' => 'active',
        ]);
    }

    public function test_existing_phone_is_blocked(): void
    {
        [$owner, $shop] = $this->ownerWithShop();
        $existing = User::factory()->create(['phone' => '+998900000099']);

        $this->actingAs($owner)->postJson("/api/v1/shops/{$shop->id}/employees", [
            'name' => 'Vali',
            'phone' => $existing->phone,
            'password' => 'secret123',
        ])->assertStatus(422)->assertJsonPath('code', 'phone_taken');
    }

    public function test_paid_seat_charges_owner_when_limit_reached(): void
    {
        // light: max_employees = 0 → birinchi xodim pulli
        [$owner, $shop] = $this->ownerWithShop('light', balance: 100000);
        $phone = '+998900000002';
        $priceLocal = app(EmployeeService::class)->seatPriceLocal();

        $store = $this->actingAs($owner)->postJson("/api/v1/shops/{$shop->id}/employees", [
            'name' => 'Hasan',
            'phone' => $phone,
            'password' => 'secret123',
        ]);

        $store->assertOk()->assertJsonPath('data.is_paid', true);

        $this->actingAs($owner)->postJson("/api/v1/shops/{$shop->id}/employees/confirm", [
            'phone' => $phone,
            'code' => $this->latestCode($phone),
        ])->assertStatus(201)->assertJsonPath('data.employee.is_paid_seat', true);

        $this->assertEqualsWithDelta(100000 - $priceLocal, (float) $owner->fresh()->balance, 1);
        $this->assertDatabaseHas('orders', ['user_id' => $owner->id, 'type' => 'employee_seat', 'status' => 'paid']);
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $owner->id, 'type' => 'employee_seat_charge']);
    }

    public function test_paid_seat_blocked_when_insufficient_balance(): void
    {
        [$owner, $shop] = $this->ownerWithShop('light', balance: 0);

        $this->actingAs($owner)->postJson("/api/v1/shops/{$shop->id}/employees", [
            'name' => 'Kamol',
            'phone' => '+998900000003',
            'password' => 'secret123',
        ])->assertStatus(402)->assertJsonPath('code', 'insufficient_balance');
    }

    public function test_permission_gate_blocks_write_but_allows_read(): void
    {
        [$owner, $shop] = $this->ownerWithShop();
        $phone = '+998900000004';

        $this->actingAs($owner)->postJson("/api/v1/shops/{$shop->id}/employees", [
            'name' => 'Sardor',
            'phone' => $phone,
            'password' => 'secret123',
        ]);
        $this->actingAs($owner)->postJson("/api/v1/shops/{$shop->id}/employees/confirm", [
            'phone' => $phone,
            'code' => $this->latestCode($phone),
        ]);

        $employee = User::where('phone', $phone)->first();

        // Standart ruxsatlarda manage_products yo'q → yozish bloklanadi
        $this->actingAs($employee)->postJson("/api/v1/shops/{$shop->id}/bread-categories", [
            'name' => 'Yangi non',
            'selling_price' => 5000,
        ])->assertStatus(403)->assertJsonPath('code', 'forbidden_permission');

        // O'qish ochiq
        $this->actingAs($employee)->getJson("/api/v1/shops/{$shop->id}/bread-categories")->assertOk();
    }

    public function test_owner_can_update_employee_permissions(): void
    {
        [$owner, $shop] = $this->ownerWithShop();
        $phone = '+998900000005';

        $this->actingAs($owner)->postJson("/api/v1/shops/{$shop->id}/employees", [
            'name' => 'Jasur',
            'phone' => $phone,
            'password' => 'secret123',
        ]);
        $this->actingAs($owner)->postJson("/api/v1/shops/{$shop->id}/employees/confirm", [
            'phone' => $phone,
            'code' => $this->latestCode($phone),
        ]);

        $employee = User::where('phone', $phone)->first();

        $this->actingAs($owner)->putJson("/api/v1/shops/{$shop->id}/employees/{$employee->id}/permissions", [
            'permissions' => ['manage_products', 'view_reports'],
        ])->assertOk();

        // Endi mahsulot yarata oladi
        $this->actingAs($employee)->postJson("/api/v1/shops/{$shop->id}/bread-categories", [
            'name' => 'Patir',
            'selling_price' => 6000,
        ])->assertStatus(201);
    }

    public function test_seller_cannot_manage_employees(): void
    {
        [$owner, $shop] = $this->ownerWithShop();
        $phone = '+998900000006';

        $this->actingAs($owner)->postJson("/api/v1/shops/{$shop->id}/employees", [
            'name' => 'Bekzod',
            'phone' => $phone,
            'password' => 'secret123',
        ]);
        $this->actingAs($owner)->postJson("/api/v1/shops/{$shop->id}/employees/confirm", [
            'phone' => $phone,
            'code' => $this->latestCode($phone),
        ]);

        $employee = User::where('phone', $phone)->first();

        $this->actingAs($employee)->getJson("/api/v1/shops/{$shop->id}/employees")
            ->assertStatus(403)->assertJsonPath('code', 'forbidden_owner_only');
    }
}
