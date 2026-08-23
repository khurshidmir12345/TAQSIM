<?php

namespace App\Http\Resources;

use App\Enums\ShopPermission;
use App\Enums\ShopUserType;
use App\Services\AccessService;
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
            'permissions'      => $this->whenPivotLoaded('user_shops', fn () => $this->resolvePermissions()),
            // Muddat bo'yicha ochiq bo'limlar. `permissions` roldan kelib
            // chiqadi, bu esa hisob muddatidan — ikkalasi mustaqil.
            'features'         => $this->whenPivotLoaded('user_shops', fn () => $this->resolveFeatures()),
            'created_at'       => $this->created_at,
        ];
    }

    /**
     * Joriy foydalanuvchining shu do'kondagi ruxsatlari.
     * Owner doim barcha ruxsatlarga ega; seller uchun pivotdagi ro'yxat.
     */
    private function resolvePermissions(): array
    {
        $userType = $this->pivot->user_type;

        $isOwner = $userType === ShopUserType::Owner
            || $userType === ShopUserType::Owner->value;

        if ($isOwner) {
            return ShopPermission::values();
        }

        return $this->pivot->permissions ?? [];
    }

    /**
     * Muddat bo'yicha ochiq bo'limlar (`config/access.php` dagi `paid_features`).
     *
     * Ro'yxat egasining muddatidan hisoblanadi — xodim ham xuddi shu ro'yxatni
     * oladi. Ilova buni o'qib bo'limni ochadi yoki neytral xabar ko'rsatadi.
     *
     * @return list<string>
     */
    private function resolveFeatures(): array
    {
        return app(AccessService::class)->featuresFor($this->resource);
    }
}
