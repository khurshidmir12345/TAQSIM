<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Http\Resources\WalletTransactionResource;
use App\Models\Order;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function __construct(
        private readonly SettingService $settings,
    ) {}

    /**
     * Balans to'ldirish ma'lumotlari: karta raqami, egasi, izoh.
     * Asosiy manba — AppSetting; bo'sh bo'lsa config/billing.php fallback.
     */
    public function topupInfo(): JsonResponse
    {
        $enabled = $this->settings->get('topup_enabled', '1') === '1';

        return $this->success([
            'topup_enabled' => $enabled,
            'card_number'   => $this->settings->get('topup_card_number', config('billing.topup.card_number')),
            'card_holder'   => $this->settings->get('topup_card_holder', config('billing.topup.card_holder')),
            'note'          => $this->settings->get('topup_note', config('billing.topup.note')),
        ]);
    }

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
        if ($this->settings->get('topup_enabled', '1') !== '1') {
            return $this->error(__('api.topup_disabled'), 503);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1000'],
            'receipt_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        // Chek rasmi maxfiy (private) diskka saqlanadi — to'g'ridan-to'g'ri URL yo'q.
        $receiptPath = $request->file('receipt_image')->store('receipts', 'local');

        $order = Order::create([
            'user_id' => $request->user()->id,
            'order_number' => 'TQ-' . now()->format('ymd') . '-' . strtoupper(Str::random(6)),
            'type' => OrderType::Topup->value,
            'status' => OrderStatus::Pending->value,
            'amount_local' => $data['amount'],
            'currency_code' => 'UZS',
            'payment_method' => 'manual',
            'receipt_path' => $receiptPath,
        ]);

        return $this->success([
            'order_number' => $order->order_number,
            'amount' => (float) $order->amount_local,
            'status' => $order->status,
        ], __('api.created'), 201);
    }
}
