<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Production extends Model
{
    use HasUuids;

    protected $fillable = [
        'shop_id',
        'recipe_id',
        'bread_category_id',
        'date',
        'batch_count',
        'flour_used_kg',
        'bread_produced',
        'ingredient_cost',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'batch_count' => 'decimal:2',
            'flour_used_kg' => 'decimal:2',
            'bread_produced' => 'integer',
            'ingredient_cost' => 'decimal:2',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Productions tarixiy ma'lumot — retsept yoki mahsulot soft-delete
     * qilinsa ham, ushbu ishlab chiqarish yozuvi o'z konteksti bilan
     * ko'rinishi kerak. Shu sababli `withTrashed()` ishlatamiz.
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class)->withTrashed();
    }

    public function breadCategory(): BelongsTo
    {
        return $this->belongsTo(BreadCategory::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
