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
            [
                'key' => 'topup_card_number',
                'value' => '8600 0000 0000 0000',
                'group' => 'billing',
                'label' => 'Balans to\'ldirish karta raqami',
            ],
            [
                'key' => 'topup_card_holder',
                'value' => 'TAQSEEM',
                'group' => 'billing',
                'label' => 'Karta egasi',
            ],
            [
                'key' => 'topup_note',
                'value' => 'To\'lovni amalga oshirgach, chek rasmini yuklang. Admin tasdiqlagach balans to\'ldiriladi.',
                'group' => 'billing',
                'label' => 'Balans to\'ldirish izohi',
            ],
        ];

        foreach ($defaults as $row) {
            AppSetting::query()->updateOrCreate(['key' => $row['key']], $row);
        }
    }
}
