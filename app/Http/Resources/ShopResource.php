<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'slug'             => $this->slug,
            'description'      => $this->description,
            'address'          => $this->address,
            'phone'            => $this->phone,
            'is_active'        => $this->is_active,
            'location'         => $this->latitude ? [
                'latitude'  => $this->latitude,
                'longitude' => $this->longitude,
            ] : null,
            'business_type'    => $this->whenLoaded('businessType', fn () =>
                new BusinessTypeResource($this->businessType)),
            'business_type_id' => $this->business_type_id,
            'currency_id'      => $this->currency_id,
            'currency'         => $this->whenLoaded('currency', fn () =>
                new CurrencyResource($this->currency)),
            'custom_business_type' => $this->whenLoaded('customBusinessType', fn () =>
                $this->customBusinessType?->name),
            'measurement_units' => $this->whenLoaded('measurementUnits', fn () =>
                MeasurementUnitResource::collection($this->measurementUnits)),
            'user_type'        => $this->whenPivotLoaded('user_shops', fn () => $this->pivot->user_type),
            'created_at'       => $this->created_at,
        ];
    }
}
