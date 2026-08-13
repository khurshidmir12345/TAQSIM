<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * "Parolni o'rnatish huquqi" — SMS kodi bilan kirgan foydalanuvchi eski
 * parolni bilmasdan yangisini qo'ya olishi uchun.
 *
 * Parolni unutgan odam ta'rifiga ko'ra eski parolni kirita olmaydi. Kod
 * orqali kirish esa telefon egaligini isbotlaydi — bu eski parolni bilish
 * bilan bir xil darajadagi dalil.
 */
class PasswordGrantService
{
    /** Kirgandan keyin parolni keyinroq ham qo'ya olsin — bir kun yetarli. */
    private const TTL = 86400;

    public function grant(User $user): void
    {
        Cache::put($this->key($user), true, self::TTL);
    }

    public function has(User $user): bool
    {
        return Cache::get($this->key($user)) === true;
    }

    public function forget(User $user): void
    {
        Cache::forget($this->key($user));
    }

    private function key(User $user): string
    {
        return "pwd-grant:{$user->id}";
    }
}
