<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'order_number',
        'type',
        'status',
        'plan_id',
        'plan_code',
        'amount_usd',
        'amount_local',
        'currency_code',
        'exchange_rate',
        'payment_method',
        'paid_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:2',
            'amount_local' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
