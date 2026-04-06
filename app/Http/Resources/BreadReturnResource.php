<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BreadReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'bread_category_id' => $this->bread_category_id,
            'production_id' => $this->production_id,
            'bread_category' => new BreadCategoryResource($this->whenLoaded('breadCategory')),
            'production' => $this->whenLoaded(
                'production',
                fn () => $this->production ? [
                    'id' => $this->production->id,
                    'batch_count' => $this->production->batch_count,
                    'bread_produced' => $this->production->bread_produced,
                    'date' => $this->production->date->toDateString(),
                ] : null
            ),
            'date' => $this->date->toDateString(),
            'quantity' => $this->quantity,
            'price_per_unit' => $this->price_per_unit,
            'total_amount' => $this->total_amount,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
        ];
    }
}
