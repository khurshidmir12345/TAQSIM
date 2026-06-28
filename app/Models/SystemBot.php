<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SystemBot extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'type',
        'username',
        'token',
        'webhook_url',
        'chat_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
