<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredient extends Model
{
    use HasUuids;

    protected $fillable = [
        'recipe_id',
        'ingredient_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * O'chirilgan xom ashyo ham qaytadi — retsept unga hali bog'langan bo'lishi
     * mumkin. Aks holda tannarx null'dan narx o'qib yiqilardi va retseptlar
     * ro'yxati butunlay ochilmasdi (prodda 13 foydalanuvchida bo'lgan).
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }

    public function lineCost(): float
    {
        return (float) $this->quantity * (float) $this->ingredient->price_per_unit;
    }
}
