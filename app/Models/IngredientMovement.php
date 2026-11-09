<?php

namespace App\Models;

use App\Enums\IngredientMovementType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientMovement extends Model
{
    use HasUuids;

    protected $fillable = [
        'shop_id',
        'ingredient_id',
        'type',
        'quantity',
        'unit_price',
        'total_amount',
        'production_id',
        'note',
        'date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => IngredientMovementType::class,
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'total_amount' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
