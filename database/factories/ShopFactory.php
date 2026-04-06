<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Shop> */
class ShopFactory extends Factory
{
    protected $model = Shop::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name . ' Nonvoyxona',
            'slug' => Str::slug($name) . '-' . Str::random(5),
            'description' => fake()->optional()->sentence(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
