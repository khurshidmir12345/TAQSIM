<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Ingredient;
use App\Models\MeasurementUnit;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ingredient> */
class IngredientFactory extends Factory
{
    protected $model = Ingredient::class;

    public function definition(): array
    {
        $ingredients = [
            ['name' => 'Un (1-sort)', 'unit' => 'kg', 'is_flour' => true, 'price' => 8000],
            ['name' => 'Un (oliy sort)', 'unit' => 'kg', 'is_flour' => true, 'price' => 10000],
            ['name' => 'Suv', 'unit' => 'litr', 'is_flour' => false, 'price' => 500],
            ['name' => 'Tuz', 'unit' => 'kg', 'is_flour' => false, 'price' => 3000],
            ['name' => 'Xamirturush', 'unit' => 'gram', 'is_flour' => false, 'price' => 50],
            ['name' => 'Shakar', 'unit' => 'kg', 'is_flour' => false, 'price' => 12000],
            ['name' => 'Sariyog\'', 'unit' => 'kg', 'is_flour' => false, 'price' => 60000],
            ['name' => 'Yog\'', 'unit' => 'litr', 'is_flour' => false, 'price' => 25000],
        ];

        $item = fake()->randomElement($ingredients);

        return [
            'shop_id' => Shop::factory(),
            'name' => $item['name'],
            'unit' => $item['unit'],
            'measurement_unit_id' => MeasurementUnit::query()->where('code', 'kg')->value('id'),
            'is_flour' => $item['is_flour'],
            'price_per_unit' => $item['price'],
            'currency_id' => Currency::query()->where('code', 'UZS')->value('id'),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function flour(): static
    {
        return $this->state(fn () => [
            'name' => 'Un (1-sort)',
            'unit' => 'kg',
            'is_flour' => true,
            'price_per_unit' => 8000,
        ]);
    }
}
