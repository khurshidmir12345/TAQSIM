<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BreadCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'name' => $this->name,
            'selling_price' => $this->selling_price,
            'currency_id' => $this->currency_id,
            'currency' => $this->currency
                ? new CurrencyResource($this->currency)
                : null,
            'measurement_unit_id' => $this->measurement_unit_id,
            'measurement_unit' => $this->measurementUnit
                ? new MeasurementUnitResource($this->measurementUnit)
                : null,
            'image_url' => $this->image_url,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
