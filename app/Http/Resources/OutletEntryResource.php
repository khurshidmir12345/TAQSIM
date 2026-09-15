<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OutletEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'outlet_id' => $this->outlet_id,
            'type' => $this->type->value,
            'date' => $this->date?->toDateString(),
            'amount' => (float) $this->amount,
            'related_entry_id' => $this->related_entry_id,
            'note' => $this->note,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'bread_category_id' => $i->bread_category_id,
                'name' => $i->breadCategory?->name ?? '',
                'quantity' => (float) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'subtotal' => (float) $i->subtotal,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
