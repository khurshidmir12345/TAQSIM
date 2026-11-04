<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Retseptning 1 partiyasidan chiqadigan mahsulot (odatiy miqdor).
 */
class RecipeOutput extends Model
{
    use HasUuids;

    protected $fillable = [
        'recipe_id',
        'bread_category_id',
        'quantity',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class)->withTrashed();
    }

    public function breadCategory(): BelongsTo
    {
        return $this->belongsTo(BreadCategory::class)->withTrashed();
    }
}
