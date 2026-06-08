<?php

namespace App\Models;

use App\Enums\SellerSubStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Xodim (seller) obunasi — owner obunasidan alohida.
 *
 * Seat egasi seller, to'lovchi esa owner. Bepul o'rin (is_paid_seat=false)
 * owner obunasiga bog'liq; pulli o'rin oylik to'lov bilan uzaytiriladi.
 */
class SellerSub extends Model
{
    use HasUuids;

    protected $table = 'seller_subs';

    protected $fillable = [
        'user_id',
        'shop_id',
        'owner_id',
        'status',
        'is_paid_seat',
        'price_usd',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SellerSubStatus::class,
            'is_paid_seat' => 'boolean',
            'price_usd' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Seller ishlay oladimi (obuna aktivmi). */
    public function isActive(): bool
    {
        return $this->status === SellerSubStatus::Active;
    }
}
