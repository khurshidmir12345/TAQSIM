<?php

namespace App\Models;

use App\Enums\IngredientUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'shop_id',
        'name',
        'unit',
        'measurement_unit_id',
        'is_flour',
        'price_per_unit',
        'currency_id',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit' => IngredientUnit::class,
            'is_flour' => 'boolean',
            'price_per_unit' => 'decimal:2',
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
}
