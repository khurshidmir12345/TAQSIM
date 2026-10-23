<?php

namespace Tests\Feature;

use App\Enums\ShopUserType;
use App\Models\BreadCategory;
use App\Models\Currency;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mahsulot, xom ashyo va retseptlar ro'yxatida yangi qo'shilgani tepada.
 * Tartibni server beradi — ilova o'zi saralamaydi.
 */
class NewestFirstListsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Shop $shop;
    private string $uzs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CurrencySeeder::class);

        $this->uzs = Currency::query()->where('code', 'UZS')->value('id');
        $this->owner = User::factory()->create();
        $this->shop = Shop::create(['name' => 'Non', 'slug' => 'non-' . Str::random(5), 'is_active' => true, 'currency_id' => $this->uzs]);
        $this->owner->shops()->attach($this->shop->id, ['user_type' => ShopUserType::Owner]);
    }

    private function url(string $path): string
    {
        return "/api/v1/shops/{$this->shop->id}/{$path}";
    }

    private function product(string $name): BreadCategory
    {
        return BreadCategory::create(['shop_id' => $this->shop->id, 'name' => $name, 'selling_price' => 1000, 'currency_id' => $this->uzs]);
    }

    public function test_products_newest_first(): void
    {
        $this->travelTo(now()->subDay());
        $this->product('Eski');
        $this->travelBack();
        $this->product('Yangi');

        $names = collect($this->actingAs($this->owner)->getJson($this->url('bread-categories'))
            ->assertOk()->json('data.bread_categories'))->pluck('name')->all();

        $this->assertSame(['Yangi', 'Eski'], $names);
    }

    public function test_ingredients_newest_first_even_in_same_second(): void
    {
        $this->freezeSecond();
        foreach (['Birinchi', 'Ikkinchi', 'Uchinchi'] as $name) {
            Ingredient::create(['shop_id' => $this->shop->id, 'name' => $name, 'unit' => 'kg', 'price_per_unit' => 100, 'currency_id' => $this->uzs]);
        }

        $names = collect($this->actingAs($this->owner)->getJson($this->url('ingredients'))
            ->assertOk()->json('data.ingredients'))->pluck('name')->all();

        $this->assertSame(['Uchinchi', 'Ikkinchi', 'Birinchi'], $names);
    }

    public function test_recipes_newest_first_not_alphabetical(): void
    {
        $this->travelTo(now()->subDay());
        Recipe::create(['shop_id' => $this->shop->id, 'bread_category_id' => $this->product('A')->id, 'name' => 'Arzon non', 'output_quantity' => 100]);
        $this->travelBack();
        Recipe::create(['shop_id' => $this->shop->id, 'bread_category_id' => $this->product('B')->id, 'name' => 'Yangi non', 'output_quantity' => 100]);

        $names = collect($this->actingAs($this->owner)->getJson($this->url('recipes'))
            ->assertOk()->json('data.recipes'))->pluck('name')->all();

        $this->assertSame(['Yangi non', 'Arzon non'], $names);
    }
}
