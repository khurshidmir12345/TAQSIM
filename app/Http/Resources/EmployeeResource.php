<?php

namespace App\Http\Resources;

use App\Models\UserShop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserShop */
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user_id,
            'name' => $this->user?->name,
            'phone' => $this->user?->phone,
            'permissions' => $this->permissions ?? [],
            'joined_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
