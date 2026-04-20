<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'telegram_chat_id' => $this->telegram_chat_id,
            'telegram_username' => $this->telegram_username,
            'google_id' => $this->google_id,
            'balance' => $this->balance,
            'is_accepted_policy' => $this->is_accepted_policy,
            'avatar_url' => $this->resolveAvatarUrl(),
            'locale' => $this->locale,
            'email_verified_at' => $this->email_verified_at,
            'phone_verified_at' => $this->phone_verified_at,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Avatar URL'ini normalizatsiya qiladi.
     * - Bo'sh bo'lsa: null.
     * - To'liq URL (http/https) bo'lsa (masalan OAuth provider avatar): o'zgarishsiz.
     * - Aks holda public disk'dagi path sifatida to'liq URL'ga aylantiriladi.
     */
    private function resolveAvatarUrl(): ?string
    {
        $value = $this->avatar_url;
        if (!is_string($value) || $value === '') {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }
}
