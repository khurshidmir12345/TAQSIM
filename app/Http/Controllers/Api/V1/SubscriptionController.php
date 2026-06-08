<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\SubscriptionPlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\SubscriptionPlan;
use App\Services\ExchangeRateService;
use App\Services\PlanLimitService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PlanLimitService $limits,
        private readonly ExchangeRateService $exchange,
    ) {}

    /** Sotib olinadigan tariflar (trialdan tashqari faol tariflar). */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->where('is_trial', false)
            ->orderBy('sort_order')
            ->get();

        return $this->success([
            'plans' => SubscriptionPlanResource::collection($plans),
            'exchange_rate' => $this->exchange->usdToUzs(),
            'currency_code' => 'UZS',
        ]);
    }

    /** Joriy obuna holati + limit/usage + balans. */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $this->subscriptions->current($user)
            ?? $this->subscriptions->ensureTrial($user);

        return $this->success([
            'subscription' => $subscription
                ? new SubscriptionResource($subscription->loadMissing('plan'))
                : null,
            'usage' => [
                'shops' => $this->limits->info($user, 'shops'),
                'products' => $this->limits->info($user, 'products'),
                'employees' => $this->limits->info($user, 'employees'),
            ],
            'balance' => (float) $user->balance,
            'currency_code' => 'UZS',
        ]);
    }

    /** Tarifni balansdan to'lab sotib olish. */
    public function purchase(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'uuid'],
        ]);

        $user = $request->user();

        $plan = SubscriptionPlan::query()
            ->where('is_active', true)
            ->where('is_trial', false)
            ->find($data['plan_id']);

        if (! $plan) {
            return $this->error(__('api.errors.not_found'), 404);
        }

        $priceLocal = $this->exchange->convertUsdToUzs((float) $plan->price_usd);

        if ((float) $user->balance < $priceLocal) {
            return response()->json([
                'success' => false,
                'message' => __('api.errors.insufficient_balance'),
                'code' => 'insufficient_balance',
                'data' => [
                    'price_local' => $priceLocal,
                    'balance' => (float) $user->balance,
                    'shortfall' => max(0, $priceLocal - (float) $user->balance),
                    'currency_code' => 'UZS',
                ],
            ], 402);
        }

        try {
            $result = $this->subscriptions->purchase($user, $plan);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'insufficient_balance') {
                return response()->json([
                    'success' => false,
                    'message' => __('api.errors.insufficient_balance'),
                    'code' => 'insufficient_balance',
                ], 402);
            }

            throw $e;
        }

        return $this->success([
            'subscription' => new SubscriptionResource($result['subscription']->loadMissing('plan')),
            'order' => new OrderResource($result['order']),
            'balance' => (float) $user->fresh()->balance,
        ], __('api.created'), 201);
    }
}
