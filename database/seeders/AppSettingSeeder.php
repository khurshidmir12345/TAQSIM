<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Services\EmployeeService;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'key' => EmployeeService::SETTING_PRICE_USD,
                'value' => '2',
                'group' => 'employees',
                'label' => 'Qo\'shimcha xodim o\'rni narxi (USD/oy)',
            ],
            [
                'key' => EmployeeService::SETTING_FRIDAY_DISCOUNT,
                'value' => '50',
                'group' => 'employees',
                'label' => 'Juma kuni chegirma (%)',
            ],
        ];

        foreach ($defaults as $row) {
            AppSetting::query()->updateOrCreate(['key' => $row['key']], $row);
        }
    }
}
