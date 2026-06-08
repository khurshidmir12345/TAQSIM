<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasUuids;

    protected $fillable = [
        'base_code',
        'quote_code',
        'rate',
        'source',
        'is_active',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_active' => 'boolean',
            'fetched_at' => 'datetime',
        ];
    }
}
