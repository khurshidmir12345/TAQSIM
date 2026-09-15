<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Do'kon — mahsulot tarqatiladigan nuqta. */
class Outlet extends Model
{
    use HasUuids;
    use SoftDeletes;

    public const MAX_PHONES = 3;

    protected $fillable = [
        'shop_id', 'name', 'address', 'latitude', 'longitude',
        'image_path', 'phones', 'note', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'phones' => 'array',
            'is_active' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(OutletEntry::class);
    }
}
