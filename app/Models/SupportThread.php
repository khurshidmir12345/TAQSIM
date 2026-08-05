<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Support guruhidagi forward xabar ↔ foydalanuvchi bog'lanishi.
 * Admin guruhda reply qilganda javob kimga ketishini shu yozuv aniqlaydi.
 */
class SupportThread extends Model
{
    use HasUuids;

    protected $fillable = [
        'system_bot_id',
        'group_chat_id',
        'group_message_id',
        'user_chat_id',
        'user_name',
        'user_username',
    ];

    protected function casts(): array
    {
        return [
            'group_message_id' => 'integer',
        ];
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(SystemBot::class, 'system_bot_id');
    }
}
