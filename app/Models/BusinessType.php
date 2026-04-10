<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessType extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'icon',
        'color',
        'sort_order',
        'is_active',
        'name_uz',
        'name_uz_cyrl',
        'name_ru',
        'name_kk',
        'name_ky',
        'name_tr',
        'terminology',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'terminology' => 'array',
            'sort_order'  => 'integer',
        ];
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    /**
     * Get localized name for a given locale code.
     * Falls back to Uzbek if locale not found.
     */
    public function getLocalizedName(string $locale = 'uz'): string
    {
        $map = [
            'uz'      => $this->name_uz,
            'uz_CYRL' => $this->name_uz_cyrl ?? $this->name_uz,
            'ru'      => $this->name_ru,
            'kk'      => $this->name_kk ?? $this->name_ru,
            'ky'      => $this->name_ky ?? $this->name_ru,
            'tr'      => $this->name_tr ?? $this->name_uz,
        ];

        return $map[$locale] ?? $this->name_uz;
    }

    /**
     * Get terminology for a given locale.
     * Falls back to 'uz' if locale not found.
     */
    public function getTerminology(string $locale = 'uz'): array
    {
        $term = $this->terminology ?? [];
        return $term[$locale] ?? $term['uz'] ?? [];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
