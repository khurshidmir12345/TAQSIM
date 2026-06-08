<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name_uz', 'name_uz_cyrl', 'name_ru', 'name_kk', 'name_ky', 'name_tr',
        'price_usd',
        'price_usd_yearly',
        'billing_period',
        'duration_days',
        'max_shops',
        'max_products',
        'max_employees',
        'extra_features',
        'is_trial',
        'trial_days',
        'is_active',
        'is_popular',
        'color',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_usd' => 'decimal:2',
            'price_usd_yearly' => 'decimal:2',
            'duration_days' => 'integer',
            'max_shops' => 'integer',
            'max_products' => 'integer',
            'max_employees' => 'integer',
            'extra_features' => 'array',
            'is_trial' => 'boolean',
            'trial_days' => 'integer',
            'is_active' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /** Tarif nomini berilgan til bo'yicha qaytaradi (uz fallback). */
    public function localizedName(?string $locale = null): string
    {
        $map = [
            'uz' => $this->name_uz,
            'uz_CYRL' => $this->name_uz_cyrl,
            'ru' => $this->name_ru,
            'kk' => $this->name_kk,
            'ky' => $this->name_ky,
            'tr' => $this->name_tr,
        ];

        return $map[$locale] ?? $this->name_uz;
    }
}
