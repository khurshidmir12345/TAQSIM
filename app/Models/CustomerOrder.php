<?php

namespace App\Models;

use App\Enums\CustomerOrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'shop_id',
        'customer_id',
        'status',
        'delivery_date',
        'delivery_time',
        'total_amount',
        'note',
        'delivered_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CustomerOrderStatus::class,
            'delivery_date' => 'date',
            'total_amount' => 'decimal:2',
            'delivered_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerOrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerOrderPayment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getPaidAmountAttribute(): string
    {
        if ($this->relationLoaded('payments')) {
            return number_format((float) $this->payments->sum('amount'), 2, '.', '');
        }

        return number_format((float) $this->payments()->sum('amount'), 2, '.', '');
    }

    public function getRemainingAmountAttribute(): string
    {
        $remaining = (float) $this->total_amount - (float) $this->paid_amount;

        return number_format(max(0, $remaining), 2, '.', '');
    }
}
