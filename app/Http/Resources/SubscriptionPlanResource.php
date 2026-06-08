<?php

namespace App\Http\Resources;

use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $exchange = app(ExchangeRateService::class);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->localizedName(app()->getLocale()),
            'price_usd' => (float) $this->price_usd,
            'price_local' => $exchange->convertUsdToUzs((float) $this->price_usd),
            'currency_code' => 'UZS',
            'billing_period' => $this->billing_period,
            'duration_days' => $this->duration_days,
            'limits' => [
                'shops' => $this->max_shops,
                'products' => $this->max_products,
                'employees' => $this->max_employees,
            ],
            'extra_features' => $this->extra_features ?? [],
            'is_trial' => $this->is_trial,
            'is_popular' => $this->is_popular,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
        ];
    }
}
