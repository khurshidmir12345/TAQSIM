<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'category' => $this->category,
            'category_label' => $this->categoryLabel($request),
            'description' => $this->description,
            'amount' => $this->amount,
            'date' => $this->date->toDateString(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
