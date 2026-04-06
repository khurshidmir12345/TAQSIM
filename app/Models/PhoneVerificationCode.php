<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PhoneVerificationCode extends Model
{
    use HasUuids;

    protected $fillable = [
        'phone',
        'code',
        'attempts',
        'expires_at',
        'verified_at',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'verified_at' => 'datetime',
        'attempts'    => 'integer',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function hasExceededAttempts(): bool
    {
        return $this->attempts >= 5;
    }
}
