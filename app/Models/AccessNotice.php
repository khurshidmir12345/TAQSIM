<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Telegram orqali yuborilgan bitta ogohlantirish yozuvi.
 *
 * Job har kuni ishlaydi — bu jadval "7 kun qoldi" xabari bir marta
 * yuborilishini kafolatlaydi.
 */
class AccessNotice extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'access_until',
        'days_before',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'access_until' => 'datetime',
            'days_before' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
