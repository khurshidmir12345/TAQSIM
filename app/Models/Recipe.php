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

    /** Bitta mahsulot retsepti — har mahsulotga bittadan. */
    public const KIND_SINGLE = 'single';

    /** Aralash partiya: o'z xom ashyosi, bir nechta (istalgan) mahsulot. */
    public const KIND_MIX = 'mix';

    protected $attributes = [
        'kind' => self::KIND_SINGLE,
    ];

    protected $fillable = [
        'shop_id',
        'kind',
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

    public function isMix(): bool
    {
        return $this->kind === self::KIND_MIX;
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Retsept ushbu mahsulot uchun yaratilgan. Mahsulot soft-delete bo'lsa
     * ham, tarixiy ma'lumot (productions) retsept va uning mahsulotini
     * to'g'ri ko'rsatishi kerak — `withTrashed()` qo'shamiz.
     */
    public function breadCategory(): BelongsTo
    {
        return $this->belongsTo(BreadCategory::class)->withTrashed();
    }

    public function measurementUnit(): BelongsTo
    {
        return $this->belongsTo(MeasurementUnit::class);
    }

    /**
     * 1 partiyadan chiqadigan mahsulotlar. Oddiy retseptda bitta qator
     * (= bread_category_id + output_quantity).
     */
    public function outputs(): HasMany
    {
        return $this->hasMany(RecipeOutput::class)->orderBy('sort_order')->orderBy('created_at');
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
