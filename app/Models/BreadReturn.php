<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreadReturn extends Model
{
    use HasUuids;

    protected $table = 'bread_returns';

    protected $fillable = [
        'shop_id',
        'bread_category_id',
        'production_id',
        'date',
        'quantity',
        'price_per_unit',
        'total_amount',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantity' => 'integer',
            'price_per_unit' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Vozvratlar tarixiy ma'lumot — bog'liq mahsulot soft-delete
     * qilinsa ham, qaytarish yozuvi o'z konteksti bilan ko'rinishi
     * kerak. Shu sababli `withTrashed()` ishlatamiz.
     */
    public function breadCategory(): BelongsTo
    {
        return $this->belongsTo(BreadCategory::class)->withTrashed();
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
