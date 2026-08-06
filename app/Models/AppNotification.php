<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ilova ichidagi bildirishnoma. Push yuborilgan-yuborilmaganidan qat'i nazar
 * yaratiladi — foydalanuvchi ro'yxatda baribir ko'radi.
 */
class AppNotification extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'category',
        'title',
        'body',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function scopeUnread($query): mixed
    {
        return $query->whereNull('read_at');
    }
}
