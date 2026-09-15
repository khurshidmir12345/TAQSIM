<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MeasurementUnit extends Model
{
    use HasUuids;

    /** Partiya / partiya hajmi birliklari — retsept va do‘kon sozlamalari. */
    public const BATCH_UNIT_CODES = [
        'qop', 'kg_batch', 'blok', 'bolim', 'toplam', 'quti', 'km', 'ton', 'm3',
        'dona_batch', 'l_batch', 'm_batch', 'qozon',
    ];

    /** Do'kon yaratgan maxsus birlik kodining prefiksi (`custom_ab12cd34ef56`). */
    public const CUSTOM_CODE_PREFIX = 'custom_';

    /** Bitta do'kon yarata oladigan maxsus birliklar soni. */
    public const MAX_CUSTOM_PER_SHOP = 20;

    protected $fillable = [
        'shop_id', 'type', 'code',
        'name_uz', 'name_uz_cyrl', 'name_ru', 'name_kk', 'name_ky', 'name_tr',
        'example_uz', 'example_ru',
        'icon', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_measurement_units');
    }

    /** Maxsus birlik egasi (tizim birligida `null`). */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function isCustom(): bool
    {
        return $this->shop_id !== null;
    }

    public function getLocalizedName(string $locale = 'uz'): string
    {
        return match ($locale) {
            'uz_CYRL', 'uz-Cyrl', 'uz_cyrl' => $this->name_uz_cyrl,
            'ru'                              => $this->name_ru,
            'kk'                              => $this->name_kk,
            'ky'                              => $this->name_ky,
            'tr'                              => $this->name_tr,
            default                           => $this->name_uz,
        };
    }

    public function getLocalizedExample(string $locale = 'uz'): ?string
    {
        return str_starts_with($locale, 'ru') ? $this->example_ru : $this->example_uz;
    }

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    public function scopeIngredient($query): mixed
    {
        return $query->where('type', 'ingredient');
    }

    public function scopeBatch($query): mixed
    {
        return $query->where('type', 'batch');
    }

    /** Faqat tizim birliklari (do'konga bog'lanmagan). */
    public function scopeGlobal($query): mixed
    {
        return $query->whereNull('shop_id');
    }

    /**
     * Retseptda ishlatsa bo'ladigan partiya birliklari: tizimdagi ruxsat
     * etilgan kodlar + shu do'konning o'z birliklari.
     */
    public function scopeBatchAvailableFor($query, string $shopId): mixed
    {
        return $query->batch()->where(function ($q) use ($shopId) {
            $q->where(function ($g) {
                $g->whereNull('shop_id')->whereIn('code', self::BATCH_UNIT_CODES);
            })->orWhere('shop_id', $shopId);
        });
    }
}
