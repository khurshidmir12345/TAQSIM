<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OutletResource extends JsonResource
{
    /** @var array{delivered: float, returned: float, paid: float, balance: float}|null */
    public ?array $totals = null;

    /** @param  array{delivered: float, returned: float, paid: float, balance: float}|null  $totals */
    public function withTotals(?array $totals): static
    {
        $this->totals = $totals;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'image_url' => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
            'phones' => $this->phones ?? [],
            'note' => $this->note,
            'is_active' => $this->is_active,
            'totals' => $this->totals ?? [
                'delivered' => 0.0, 'returned' => 0.0, 'paid' => 0.0, 'balance' => 0.0,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
