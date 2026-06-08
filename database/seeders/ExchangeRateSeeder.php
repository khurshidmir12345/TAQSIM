<?php

namespace Database\Seeders;

use App\Models\ExchangeRate;
use Illuminate\Database\Seeder;

class ExchangeRateSeeder extends Seeder
{
    public function run(): void
    {
        ExchangeRate::updateOrCreate(
            ['base_code' => 'USD', 'quote_code' => 'UZS'],
            [
                'rate' => (float) config('billing.default_usd_uzs', 12600),
                'source' => 'manual',
                'is_active' => true,
                'fetched_at' => now(),
            ],
        );
    }
}
