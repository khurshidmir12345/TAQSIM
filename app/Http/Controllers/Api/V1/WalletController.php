<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Resources\WalletTransactionResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    /** Joriy balans. */
    public function show(Request $request): JsonResponse
    {
        return $this->success([
            'balance' => (float) $request->user()->balance,
            'currency_code' => 'UZS',
        ]);
    }

    /** Balans tarixi (balansHistory) — sahifalangan. */
    public function transactions(Request $request): JsonResponse
    {
        $perPage = min(50, max(5, (int) $request->integer('per_page', 20)));

        $paginator = $request->user()
            ->walletTransactions()
            ->paginate($perPage);

        $paginator->through(fn ($txn) => (new WalletTransactionResource($txn))->resolve());

        return $this->paginated($paginator);
    }

    /**
     * Balansni to'ldirish so'rovi (1-bosqich: kutilayotgan order yaratiladi,
     * mablag'ni admin qo'lda yoki to'lov tizimi keyinroq tasdiqlaydi).
     */
    public function topup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1000'],
        ]);

        $order = Order::create([
            'user_id' => $request->user()->id,
            'order_number' => 'TQ-' . now()->format('ymd') . '-' . strtoupper(Str::random(6)),
            'type' => OrderType::Topup->value,
            'status' => OrderStatus::Pending->value,
            'amount_local' => $data['amount'],
            'currency_code' => 'UZS',
            'payment_method' => 'manual',
        ]);

        return $this->success([
            'order_number' => $order->order_number,
            'amount' => (float) $order->amount_local,
            'status' => $order->status,
        ], __('api.created'), 201);
    }
}
