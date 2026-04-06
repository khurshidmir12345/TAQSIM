<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Expense> */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        $categories = ['transport', 'yoqilg\'i', 'kommunal', 'maosh', 'ta\'mirlash', 'boshqa'];

        return [
            'shop_id' => Shop::factory(),
            'category' => fake()->randomElement($categories),
            'description' => fake()->optional()->sentence(),
            'amount' => fake()->randomFloat(2, 10000, 500000),
            'date' => fake()->dateTimeBetween('-30 days', 'now'),
            'created_by' => User::factory(),
        ];
    }
}
