<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'avatar_url' => $this->avatar_url,
            'locale' => $this->locale,
            'email_verified_at' => $this->email_verified_at,
            'phone_verified_at' => $this->phone_verified_at,
            'created_at' => $this->created_at,
        ];
    }
}
