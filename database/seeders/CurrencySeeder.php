<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'UZS', 'name' => "O'zbek so'mi", 'symbol' => "so'm", 'sort_order' => 10],
            ['code' => 'KZT', 'name' => 'Qozog‘iston tenge', 'symbol' => '₸', 'sort_order' => 20],
            ['code' => 'KGS', 'name' => 'Qirg‘iz somi', 'symbol' => 'с', 'sort_order' => 30],
            ['code' => 'RUB', 'name' => 'Rossiya rubli', 'symbol' => '₽', 'sort_order' => 40],
            ['code' => 'TJS', 'name' => 'Tojik somoni', 'symbol' => 'ЅМ', 'sort_order' => 50],
            ['code' => 'USD', 'name' => 'AQSH dollari', 'symbol' => '$', 'sort_order' => 60],
        ];

        foreach ($rows as $row) {
            Currency::query()->firstOrCreate(
                ['code' => $row['code']],
                $row
            );
        }
    }
}
