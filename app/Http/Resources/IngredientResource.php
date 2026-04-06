<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IngredientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unit = $this->unit;
        if ($unit instanceof BackedEnum) {
            $unit = $unit->value;
        }

        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'name' => $this->name,
            'unit' => $unit,
            'measurement_unit_id' => $this->measurement_unit_id,
            'measurement_unit' => $this->measurementUnit
                ? new MeasurementUnitResource($this->measurementUnit)
                : null,
            'is_flour' => $this->is_flour,
            'price_per_unit' => $this->price_per_unit,
            'currency_id' => $this->currency_id,
            'currency' => $this->currency
                ? new CurrencyResource($this->currency)
                : null,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
