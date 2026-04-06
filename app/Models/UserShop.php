<?php

namespace App\Models;

use App\Enums\ShopUserType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserShop extends Pivot
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'user_shops';

    protected $fillable = [
        'user_id',
        'shop_id',
        'user_type',
    ];

    protected function casts(): array
    {
        return [
            'user_type' => ShopUserType::class,
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
}
