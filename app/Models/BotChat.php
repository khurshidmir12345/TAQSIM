<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bot a'zo bo'lgan guruh/kanal. Bir bot bir nechta guruhda bo'lishi mumkin,
 * har biri o'z vazifasi (`purpose`) bilan.
 */
class BotChat extends Model
{
    use HasUuids;

    /** Kod va ro'yxatdan o'tish bildirishnomalari yuboriladigan guruh. */
    public const PURPOSE_NOTIFY = 'notify';

    /** Foydalanuvchi savollari forward qilinadigan guruh. */
    public const PURPOSE_SUPPORT = 'support';

    protected $fillable = [
        'system_bot_id',
        'chat_id',
        'title',
        'purpose',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(SystemBot::class, 'system_bot_id');
    }

    public function scopeActive($query): mixed
    {
        return $query->where('is_active', true);
    }

    public function scopePurpose($query, string $purpose): mixed
    {
        return $query->where('purpose', $purpose);
    }
}
