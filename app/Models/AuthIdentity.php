<?php

namespace App\Models;

use App\Enums\AuthProvider;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthIdentity extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_subject',
        'metadata',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => AuthProvider::class,
            'metadata' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
