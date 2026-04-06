<?php

namespace Database\Factories;

use App\Models\BreadCategory;
use App\Models\Currency;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BreadCategory> */
class BreadCategoryFactory extends Factory
{
    protected $model = BreadCategory::class;

    public function definition(): array
    {
        $breads = ['Oq non', 'Patir', 'Tandir non', 'Lavash', 'Toki non', 'Kulcha', 'Somsa non', 'Jo\'xori non'];

        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->randomElement($breads),
            'selling_price' => fake()->randomElement([3000, 4000, 5000, 6000, 7000, 8000]),
            'currency_id' => Currency::query()->where('code', 'UZS')->value('id'),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}
