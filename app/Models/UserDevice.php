<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Foydalanuvchi qurilmasi (multi-device sessiya). Har bir yozuv bitta aktiv
 * Sanctum tokeniga (sessiyaga) bog'lanadi.
 */
class UserDevice extends Model
{
    use HasUuids;

    protected $table = 'user_devices';

    protected $fillable = [
        'user_id',
        'token_id',
        'device_name',
        'platform',
        'push_token',
        'push_token_updated_at',
        'app_version',
        'ip',
        'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'last_active_at' => 'datetime',
            'push_token_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'token_id');
    }
}
