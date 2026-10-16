<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Models\BusinessType;
use App\Models\Currency;
use App\Models\MeasurementUnit;
use App\Models\Recipe;
use App\Models\Shop;
use App\Models\User;
use App\Services\ProductionService;
use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\MeasurementUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Yangi foydalanuvchining birinchi biznesiga namunaviy mahsulot, xom ashyo
 * va retseptlar qo'shiladi; eski foydalanuvchilarga — yo'q.
 */
class DefaultCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BusinessTypeSeeder::class);
        $this->seed(CurrencySeeder::class);
        $this->seed(MeasurementUnitSeeder::class);

        $this->user = User::factory()->create();
    }

    private function createShop(string $type = 'bakery', string $currency = 'UZS', string $lang = 'uz'): Shop
    {
        $this->actingAs($this->user)
            ->withHeader('Accept-Language', $lang)
            ->postJson('/api/v1/shops', [
                'business_type_id' => BusinessType::query()->where('key', $type)->value('id'),
                'currency_id' => Currency::query()->where('code', $currency)->value('id'),
                'name' => 'Yangi ' . Str::random(4),
            ])
            ->assertCreated();

        return Shop::query()->latest('created_at')->latest('id')->firstOrFail();
    }

    public function test_first_bakery_gets_two_products_with_recipes(): void
    {
        $shop = $this->createShop();

        $this->assertEqualsCanonicalizing(
            ['Katta non', 'Kichik non'],
            $shop->breadCategories()->pluck('name')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['Un', 'Tuz', 'Xamirturush (droj)', 'Yog\'', 'Kunjut'],
            $shop->ingredients()->pluck('name')->all(),
        );
        $this->assertTrue((bool) $shop->ingredients()->where('name', 'Un')->value('is_flour'));
        $this->assertSame(2, $shop->recipes()->count());

        // Katta non: 1 qop = 50×5500 + 1×2000 + 0.3×45000 + 2×22000 + 0.5×28000 = 348 500 → 170 dona.
        $big = Recipe::query()->where('shop_id', $shop->id)->where('name', 'Katta non')->firstOrFail();
        $this->assertSame(170, $big->output_quantity);
        $this->assertSame(MeasurementUnit::query()->where('code', 'qop')->value('id'), $big->measurement_unit_id);
        $service = app(ProductionService::class);
        $this->assertEqualsWithDelta(348500, $service->calculateIngredientCost($big, 1), 0.01);
        $this->assertEqualsWithDelta(50, $service->calculateFlourUsed($big, 1), 0.001);
        $this->assertEqualsWithDelta(4000, (float) $big->breadCategory->selling_price, 0.01);
    }

    public function test_names_follow_app_language(): void
    {
        $shop = $this->createShop(lang: 'ru');

        $this->assertEqualsCanonicalizing(
            ['Большая лепёшка', 'Маленькая лепёшка'],
            $shop->breadCategories()->pluck('name')->all(),
        );
        $this->assertTrue($shop->ingredients()->where('name', 'Мука')->exists());
    }

    public function test_other_currency_gets_quantities_without_prices(): void
    {
        $code = Currency::query()->where('code', '!=', 'UZS')->value('code');
        if ($code === null) {
            $this->markTestSkipped('Test bazasida boshqa valyuta yo\'q.');
        }

        $shop = $this->createShop(currency: $code);

        $this->assertSame(2, $shop->breadCategories()->count());
        $this->assertSame(0, $shop->ingredients()->where('price_per_unit', '>', 0)->count());
        $this->assertSame(0, $shop->breadCategories()->where('selling_price', '>', 0)->count());
    }

    public function test_business_without_template_stays_empty(): void
    {
        $shop = $this->createShop(type: 'shashlik');

        $this->assertSame(0, $shop->breadCategories()->count());
        $this->assertSame(0, $shop->ingredients()->count());
    }

    public function test_existing_user_new_shop_gets_nothing(): void
    {
        $old = Shop::create(['name' => 'Eski', 'slug' => 'eski-' . Str::random(5), 'is_active' => true]);
        $this->user->shops()->attach($old->id, ['user_type' => ShopUserType::Owner]);
        $this->user->forceFill(['access_until' => now()->addMonth(), 'access_source' => 'paid'])->save();

        $shop = $this->createShop();

        $this->assertNotSame($old->id, $shop->id);
        $this->assertSame(0, $shop->breadCategories()->count());
        $this->assertSame(0, $shop->recipes()->count());
    }
}
