<?php

namespace Database\Seeders;

use App\Enums\ShopUserType;
use App\Models\BreadCategory;
use App\Models\Currency;
use App\Models\Ingredient;
use App\Models\MeasurementUnit;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BusinessTypeSeeder::class);
        $this->call(MeasurementUnitSeeder::class);
        $this->call(CurrencySeeder::class);

        $owner = User::factory()->create([
            'name' => 'Admin',
            'phone' => '+998901234567',
            'email' => 'admin@nonvoyxona.uz',
        ]);

        $seller = User::factory()->create([
            'name' => 'Sotuvchi',
            'phone' => '+998907654321',
        ]);

        $uzsId = Currency::query()->where('code', 'UZS')->value('id');

        $shop = Shop::create([
            'name' => 'Markaziy Nonvoyxona',
            'slug' => 'markaziy-nonvoyxona-' . Str::random(5),
            'description' => 'Shahar markazidagi asosiy nonvoyxona',
            'address' => 'Toshkent sh., Amir Temur ko\'chasi, 1',
            'phone' => '+998712345678',
            'is_active' => true,
            'currency_id' => $uzsId,
        ]);

        $owner->shops()->attach($shop->id, ['user_type' => ShopUserType::Owner]);
        $seller->shops()->attach($shop->id, ['user_type' => ShopUserType::Seller]);

        $categories = [
            ['name' => 'Oq non', 'selling_price' => 4000, 'sort_order' => 1],
            ['name' => 'Patir', 'selling_price' => 6000, 'sort_order' => 2],
            ['name' => 'Tandir non', 'selling_price' => 5000, 'sort_order' => 3],
            ['name' => 'Lavash', 'selling_price' => 3000, 'sort_order' => 4],
            ['name' => 'Kulcha', 'selling_price' => 7000, 'sort_order' => 5],
        ];

        foreach ($categories as $cat) {
            BreadCategory::create(array_merge($cat, [
                'shop_id' => $shop->id,
                'currency_id' => $uzsId,
            ]));
        }

        $muKg = MeasurementUnit::query()->where('code', 'kg')->value('id');
        $muLitr = MeasurementUnit::query()->where('code', 'l')->value('id');

        $ingredients = [
            ['name' => 'Un (1-sort)', 'unit' => 'kg', 'is_flour' => true, 'price_per_unit' => 8000, 'sort_order' => 1],
            ['name' => 'Un (oliy sort)', 'unit' => 'kg', 'is_flour' => true, 'price_per_unit' => 10000, 'sort_order' => 2],
            ['name' => 'Suv', 'unit' => 'litr', 'is_flour' => false, 'price_per_unit' => 500, 'sort_order' => 3],
            ['name' => 'Tuz', 'unit' => 'kg', 'is_flour' => false, 'price_per_unit' => 3000, 'sort_order' => 4],
            ['name' => 'Xamirturush', 'unit' => 'kg', 'is_flour' => false, 'price_per_unit' => 50000, 'sort_order' => 5],
            ['name' => 'Shakar', 'unit' => 'kg', 'is_flour' => false, 'price_per_unit' => 12000, 'sort_order' => 6],
            ['name' => 'Sariyog\'', 'unit' => 'kg', 'is_flour' => false, 'price_per_unit' => 60000, 'sort_order' => 7],
            ['name' => 'Yog\'', 'unit' => 'litr', 'is_flour' => false, 'price_per_unit' => 25000, 'sort_order' => 8],
        ];

        foreach ($ingredients as $ing) {
            $muId = $ing['unit'] === 'litr' ? $muLitr : $muKg;
            Ingredient::create(array_merge($ing, [
                'shop_id' => $shop->id,
                'currency_id' => $uzsId,
                'measurement_unit_id' => $muId,
            ]));
        }

        $flour = Ingredient::where('shop_id', $shop->id)->where('name', 'Un (1-sort)')->first();
        $water = Ingredient::where('shop_id', $shop->id)->where('name', 'Suv')->first();
        $salt = Ingredient::where('shop_id', $shop->id)->where('name', 'Tuz')->first();
        $yeast = Ingredient::where('shop_id', $shop->id)->where('name', 'Xamirturush')->first();
        $oqNon = BreadCategory::where('shop_id', $shop->id)->where('name', 'Oq non')->first();

        if ($flour && $water && $salt && $yeast && $oqNon) {
            $qopId = MeasurementUnit::query()->where('code', 'qop')->value('id');
            $recipe = $shop->recipes()->create([
                'name' => 'Standart oq non retsepti',
                'bread_category_id' => $oqNon->id,
                'measurement_unit_id' => $qopId,
                'output_quantity' => 100,
                'is_active' => true,
            ]);

            $recipe->recipeIngredients()->createMany([
                ['ingredient_id' => $flour->id, 'quantity' => 50],
                ['ingredient_id' => $water->id, 'quantity' => 30],
                ['ingredient_id' => $salt->id, 'quantity' => 1],
                ['ingredient_id' => $yeast->id, 'quantity' => 500],
            ]);
        }
    }
}
