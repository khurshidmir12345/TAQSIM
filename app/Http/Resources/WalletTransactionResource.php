<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WalletTransaction */
class WalletTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'is_credit' => (float) $this->amount >= 0,
            'balance_after' => (float) $this->balance_after,
            'currency_code' => $this->currency_code,
            'description' => $this->description,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
