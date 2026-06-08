<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\WalletTransactionType;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SubscriptionService
{
    public function __construct(
        private readonly ExchangeRateService $exchange,
        private readonly WalletService $wallet,
    ) {}

    public function graceDays(): int
    {
        return (int) config('billing.grace_days', 3);
    }

    public function trialPlan(): ?SubscriptionPlan
    {
        return SubscriptionPlan::query()
            ->where('is_trial', true)
            ->where('is_active', true)
            ->first();
    }

    public function current(User $user): ?Subscription
    {
        return $user->currentSubscription()->with('plan')->first();
    }

    /**
     * Egaga (owner) avtomat bepul trial beradi (faqat obunasi yo'q bo'lsa).
     *
     * Xodimlarga (seller) TRIAL berilmaydi — ularning kirishi `seller_subs`
     * jadvalidagi obunaga bog'liq, owner trialidan butunlay alohida.
     */
    public function ensureTrial(User $user): ?Subscription
    {
        // Xodim (faqat seller, do'kon egasi emas) — trialdan butunlay chetlatiladi.
        if ($user->isSeller() && ! $user->isShopOwner()) {
            return null;
        }

        if ($user->subscriptions()->exists()) {
            return null;
        }

        $plan = $this->trialPlan();

        if (! $plan) {
            return null;
        }

        $days = $plan->trial_days > 0 ? $plan->trial_days : (int) config('billing.trial_days', 30);
        $now = now();
        $end = $now->copy()->addDays($days);

        return Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_code' => $plan->code,
            'status' => 'trialing',
            'is_current' => true,
            'starts_at' => $now,
            'ends_at' => $end,
            'trial_ends_at' => $end,
            'grace_ends_at' => $end->copy()->addDays($this->graceDays()),
        ]);
    }

    /**
     * Tarifni balansdan to'lab sotib oladi. Atomar: order + ledger + obuna.
     * Balans yetmasa RuntimeException('insufficient_balance').
     */
    public function purchase(User $user, SubscriptionPlan $plan): array
    {
        $amountUsd = (float) $plan->price_usd;
        $rate = $this->exchange->usdToUzs();
        $amountLocal = $this->exchange->convertUsdToUzs($amountUsd);

        if (! $this->wallet->hasSufficientBalance($user, $amountLocal)) {
            throw new RuntimeException('insufficient_balance');
        }

        return DB::transaction(function () use ($user, $plan, $rate, $amountLocal, $amountUsd) {
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => $this->generateOrderNumber(),
                'type' => OrderType::Subscription->value,
                'status' => OrderStatus::Paid->value,
                'plan_id' => $plan->id,
                'plan_code' => $plan->code,
                'amount_usd' => $amountUsd,
                'amount_local' => $amountLocal,
                'currency_code' => 'UZS',
                'exchange_rate' => $rate,
                'payment_method' => 'balance',
                'paid_at' => now(),
                'meta' => ['period' => $plan->billing_period],
            ]);

            $this->wallet->debit(
                $user,
                $amountLocal,
                WalletTransactionType::SubscriptionCharge,
                "Obuna: {$plan->name_uz}",
                $order,
            );

            $subscription = $this->activatePlan($user, $plan);

            return ['order' => $order, 'subscription' => $subscription];
        });
    }

    /**
     * Tarifni faollashtiradi. Agar joriy obuna hali tugamagan bo'lsa,
     * yangi davr o'sha tugash sanasidan boshlab qo'shiladi (stacking).
     */
    public function activatePlan(User $user, SubscriptionPlan $plan, ?int $durationDays = null): Subscription
    {
        $now = now();
        $current = $this->current($user);

        $base = $now;
        if ($current && $current->ends_at && $current->ends_at->greaterThan($now)) {
            $base = $current->ends_at->copy();
        }

        $end = $base->copy()->addDays($durationDays ?? $plan->duration_days);

        $user->subscriptions()->where('is_current', true)->update(['is_current' => false]);

        return Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_code' => $plan->code,
            'status' => 'active',
            'is_current' => true,
            'starts_at' => $now,
            'ends_at' => $end,
            'trial_ends_at' => null,
            'grace_ends_at' => $end->copy()->addDays($this->graceDays()),
        ]);
    }

    private function generateOrderNumber(): string
    {
        return 'TQ-' . now()->format('ymd') . '-' . strtoupper(Str::random(6));
    }
}
