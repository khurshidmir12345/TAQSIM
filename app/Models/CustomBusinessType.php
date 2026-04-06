<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomBusinessType extends Model
{
    use HasUuids;

    protected $fillable = ['shop_id', 'name'];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
