<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'trial',
                'name_uz' => 'Bepul sinov',
                'name_uz_cyrl' => 'Бепул синов',
                'name_ru' => 'Пробный период',
                'name_kk' => 'Тегін сынақ',
                'name_ky' => 'Акысыз сыноо',
                'name_tr' => 'Ücretsiz deneme',
                'price_usd' => 0,
                'duration_days' => 30,
                'max_shops' => null,
                'max_products' => null,
                'max_employees' => null,
                'is_trial' => true,
                'trial_days' => 30,
                'is_active' => true,
                'is_popular' => false,
                'color' => '#64748B',
                'sort_order' => 0,
            ],
            [
                'code' => 'light',
                'name_uz' => 'Light',
                'name_uz_cyrl' => 'Лайт',
                'name_ru' => 'Light',
                'name_kk' => 'Light',
                'name_ky' => 'Light',
                'name_tr' => 'Light',
                'price_usd' => 2.5,
                'duration_days' => 30,
                'max_shops' => 1,
                'max_products' => 5,
                'max_employees' => 0,
                'is_trial' => false,
                'is_active' => true,
                'is_popular' => false,
                'color' => '#00A896',
                'sort_order' => 1,
            ],
            [
                'code' => 'standart',
                'name_uz' => 'Standart',
                'name_uz_cyrl' => 'Стандарт',
                'name_ru' => 'Стандарт',
                'name_kk' => 'Стандарт',
                'name_ky' => 'Стандарт',
                'name_tr' => 'Standart',
                'price_usd' => 5,
                'duration_days' => 30,
                'max_shops' => 2,
                'max_products' => 15,
                'max_employees' => 1,
                'is_trial' => false,
                'is_active' => true,
                'is_popular' => true,
                'color' => '#0B3C5D',
                'sort_order' => 2,
            ],
            [
                'code' => 'premium',
                'name_uz' => 'Premium',
                'name_uz_cyrl' => 'Премиум',
                'name_ru' => 'Премиум',
                'name_kk' => 'Премиум',
                'name_ky' => 'Премиум',
                'name_tr' => 'Premium',
                'price_usd' => 20,
                'duration_days' => 30,
                'max_shops' => null,
                'max_products' => null,
                'max_employees' => 5,
                'is_trial' => false,
                'is_active' => true,
                'is_popular' => false,
                'color' => '#7C3AED',
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
