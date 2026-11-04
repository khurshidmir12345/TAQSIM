<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeOutputResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bread_category_id' => $this->bread_category_id,
            'bread_category' => new BreadCategoryResource($this->whenLoaded('breadCategory')),
            'quantity' => $this->quantity,
        ];
    }
}
