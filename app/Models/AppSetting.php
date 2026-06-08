<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'value',
        'group',
        'label',
    ];

    protected static function booted(): void
    {
        $forget = fn (AppSetting $s) => Cache::forget('app_setting:' . $s->key);

        static::saved($forget);
        static::deleted($forget);
    }
}
