<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Subscription */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->effectiveStatus();

        return [
            'id' => $this->id,
            'status' => $status->value,
            'is_trial' => (bool) ($this->plan?->is_trial),
            'has_full_access' => $status->hasFullAccess(),
            'is_read_only' => $status->isReadOnly(),
            'is_blocked' => $status->isBlocked(),
            'days_left' => $this->daysLeft(),
            'grace_days_left' => $this->graceDaysLeft(),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'trial_ends_at' => $this->trial_ends_at,
            'grace_ends_at' => $this->grace_ends_at,
            'plan' => $this->whenLoaded('plan', fn () => new SubscriptionPlanResource($this->plan)),
        ];
    }
}
