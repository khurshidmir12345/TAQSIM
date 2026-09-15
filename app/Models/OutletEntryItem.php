<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletEntryItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'outlet_entry_id', 'bread_category_id', 'quantity', 'unit_price', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(OutletEntry::class, 'outlet_entry_id');
    }

    public function breadCategory(): BelongsTo
    {
        return $this->belongsTo(BreadCategory::class);
    }
}
