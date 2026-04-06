<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'name' => $this->name,
            'bread_category' => new BreadCategoryResource($this->whenLoaded('breadCategory')),
            'measurement_unit' => new MeasurementUnitResource($this->whenLoaded('measurementUnit')),
            'output_quantity' => $this->output_quantity,
            'is_active' => $this->is_active,
            'ingredients' => RecipeIngredientResource::collection($this->whenLoaded('recipeIngredients')),
            'total_cost' => $this->when($this->relationLoaded('recipeIngredients'), fn () => $this->totalCost()),
            'cost_per_bread' => $this->when($this->relationLoaded('recipeIngredients'), fn () => $this->costPerBread()),
            'flour_per_batch' => $this->when($this->relationLoaded('recipeIngredients'), fn () => $this->flourPerBatch()),
            'created_at' => $this->created_at,
        ];
    }
}
