<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'shop_id',
        'bread_category_id',
        'measurement_unit_id',
        'name',
        'output_quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'output_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function breadCategory(): BelongsTo
    {
        return $this->belongsTo(BreadCategory::class);
    }

    public function measurementUnit(): BelongsTo
    {
        return $this->belongsTo(MeasurementUnit::class);
    }

    public function recipeIngredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    /**
     * Total cost of all ingredients for 1 batch.
     */
    public function totalCost(): float
    {
        return $this->recipeIngredients->sum(function (RecipeIngredient $ri) {
            return $ri->quantity * $ri->ingredient->price_per_unit;
        });
    }

    /**
     * Cost per single output unit for 1 batch.
     */
    public function costPerBread(): float
    {
        if ($this->output_quantity <= 0) {
            return 0;
        }

        return $this->totalCost() / $this->output_quantity;
    }

    /**
     * Total flour (kg) used in 1 batch (sum of flour-type ingredients).
     */
    public function flourPerBatch(): float
    {
        return $this->recipeIngredients
            ->filter(fn (RecipeIngredient $ri) => $ri->ingredient->is_flour)
            ->sum(fn (RecipeIngredient $ri) => (float) $ri->quantity);
    }
}
