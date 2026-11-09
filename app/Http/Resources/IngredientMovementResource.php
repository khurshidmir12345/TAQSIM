<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ing = $this->ingredient;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'ingredient_id' => $this->ingredient_id,
            'ingredient_name' => $ing?->name,
            'unit' => $ing?->measurementUnit?->code ?? $ing?->unit?->value,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'total_amount' => (float) $this->total_amount,
            'production_id' => $this->production_id,
            'note' => $this->note,
            'date' => $this->date->toDateString(),
            'created_by_name' => $this->creator?->name,
            'created_at' => $this->created_at,
        ];
    }
}
