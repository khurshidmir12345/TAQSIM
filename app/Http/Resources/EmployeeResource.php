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
        /** @var \App\Models\SellerSub|null $sub */
        $sub = $this->sellerSub;

        return [
            'id' => $this->user_id,
            'name' => $this->user?->name,
            'phone' => $this->user?->phone,
            'permissions' => $this->permissions ?? [],
            'is_paid_seat' => (bool) ($sub?->is_paid_seat ?? false),
            'seat_status' => $sub?->status?->value,
            'seat_ends_at' => $sub?->ends_at?->toIso8601String(),
            'is_suspended' => $sub !== null && ! $sub->isActive(),
            'joined_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
