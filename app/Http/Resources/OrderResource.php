<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'type' => $this->type,
            'status' => $this->status,
            'plan_code' => $this->plan_code,
            'plan_name' => $this->whenLoaded('plan', fn () => $this->plan?->localizedName(app()->getLocale())),
            'amount_usd' => $this->amount_usd !== null ? (float) $this->amount_usd : null,
            'amount_local' => (float) $this->amount_local,
            'currency_code' => $this->currency_code,
            'payment_method' => $this->payment_method,
            'reject_reason' => $this->reject_reason,
            'has_receipt' => $this->receipt_path !== null,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
