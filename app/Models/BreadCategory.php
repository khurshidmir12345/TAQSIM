<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BreadCategory extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'shop_id',
        'name',
        'selling_price',
        'currency_id',
        'measurement_unit_id',
        'image_url',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function measurementUnit(): BelongsTo
    {
        return $this->belongsTo(MeasurementUnit::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }

    public function breadReturns(): HasMany
    {
        return $this->hasMany(BreadReturn::class);
    }

    /**
     * Mahsulot o'chirilganda unga tegishli retseptlarni ham soft-delete qilamiz.
     * Retsept aynan shu mahsulot uchun yaratilgani sababli, mahsulotsiz retsept
     * mantiqqa zid. Productions/returns tarixiy ma'lumot — tegmaymiz.
     */
    protected static function booted(): void
    {
        static::deleting(function (BreadCategory $category): void {
            if ($category->isForceDeleting()) {
                return;
            }
            $category->recipes()->get()->each(
                fn (Recipe $recipe) => $recipe->delete()
            );
        });
    }
}
