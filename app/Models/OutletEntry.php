<?php

namespace App\Models;

use App\Enums\OutletEntryType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Do'kon daftaridagi bitta qator: berildi / qaytdi / to'landi. */
class OutletEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'shop_id', 'outlet_id', 'type', 'date', 'amount',
        'related_entry_id', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => OutletEntryType::class,
            'date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OutletEntryItem::class);
    }

    /** Delivery bilan birga darhol to'langan pul qatori. */
    public function relatedPayment(): HasMany
    {
        return $this->hasMany(OutletEntry::class, 'related_entry_id');
    }
}
