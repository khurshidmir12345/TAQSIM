<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeIngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ingredient_id' => $this->ingredient_id,
            'ingredient' => new IngredientResource($this->whenLoaded('ingredient')),
            'quantity' => $this->quantity,
            'line_cost' => $this->when($this->relationLoaded('ingredient'), fn () => $this->lineCost()),
        ];
    }
}
