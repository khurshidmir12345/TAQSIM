<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'plan_id',
        'plan_code',
        'status',
        'is_current',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'grace_ends_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Joriy vaqt va timestamps asosida haqiqiy holatni hisoblaydi.
     * Bu — gating uchun yagona haqiqat manbasi (cron shart emas).
     */
    public function effectiveStatus(): SubscriptionStatus
    {
        if ($this->cancelled_at !== null) {
            return SubscriptionStatus::Cancelled;
        }

        $now = now();

        if ($this->ends_at !== null && $now->lessThanOrEqualTo($this->ends_at)) {
            $isTrial = $this->trial_ends_at !== null
                && $now->lessThanOrEqualTo($this->trial_ends_at)
                && (bool) ($this->plan?->is_trial);

            return $isTrial ? SubscriptionStatus::Trialing : SubscriptionStatus::Active;
        }

        if ($this->grace_ends_at !== null && $now->lessThanOrEqualTo($this->grace_ends_at)) {
            return SubscriptionStatus::Grace;
        }

        return SubscriptionStatus::Expired;
    }

    /** To'liq kirish tugashiga qancha kun qolgani (ends_at gacha). */
    public function daysLeft(): int
    {
        if ($this->ends_at === null) {
            return 0;
        }

        return max(0, (int) ceil(now()->floatDiffInDays($this->ends_at, false)));
    }

    public function graceDaysLeft(): int
    {
        if ($this->grace_ends_at === null) {
            return 0;
        }

        return max(0, (int) ceil(now()->floatDiffInDays($this->grace_ends_at, false)));
    }
}
