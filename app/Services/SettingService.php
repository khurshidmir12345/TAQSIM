<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin paneldan boshqariladigan key/value sozlamalar.
 * Qiymatlar keshlanadi; yangilanganda kesh tozalanadi.
 */
class SettingService
{
    private const CACHE_PREFIX = 'app_setting:';

    public function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(
            self::CACHE_PREFIX . $key,
            fn () => AppSetting::query()->where('key', $key)->value('value'),
        );

        return $value ?? $default;
    }

    public function getFloat(string $key, float $default = 0): float
    {
        $value = $this->get($key);

        return $value === null ? $default : (float) $value;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        return $value === null ? $default : (int) $value;
    }

    public function set(string $key, mixed $value, string $group = 'general', ?string $label = null): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value, 'group' => $group, 'label' => $label],
        );

        Cache::forget(self::CACHE_PREFIX . $key);
    }

    public function forget(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX . $key);
    }
}
