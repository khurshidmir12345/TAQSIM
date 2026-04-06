<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'recipe_id' => $this->recipe_id,
            'bread_category_id' => $this->bread_category_id,
            'bread_category' => new BreadCategoryResource($this->whenLoaded('breadCategory')),
            'recipe' => new RecipeResource($this->whenLoaded('recipe')),
            'date' => $this->date->toDateString(),
            'batch_count' => $this->batch_count,
            'flour_used_kg' => $this->flour_used_kg,
            'bread_produced' => $this->bread_produced,
            'ingredient_cost' => $this->ingredient_cost,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
