<?php

namespace App\Services;

use App\Enums\WalletTransactionType;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    /** Balansga kirim (credit). Musbat summa. */
    public function credit(
        User $user,
        float $amount,
        WalletTransactionType $type,
        ?string $description = null,
        ?Order $order = null,
        ?User $createdBy = null,
        array $meta = [],
    ): WalletTransaction {
        return $this->record($user, abs($amount), $type, $description, $order, $createdBy, $meta);
    }

    /** Balansdan chiqim (debit). Yetarli mablag' bo'lmasa xato. */
    public function debit(
        User $user,
        float $amount,
        WalletTransactionType $type,
        ?string $description = null,
        ?Order $order = null,
        array $meta = [],
    ): WalletTransaction {
        return $this->record($user, -abs($amount), $type, $description, $order, null, $meta);
    }

    public function hasSufficientBalance(User $user, float $amount): bool
    {
        return (float) $user->balance >= $amount;
    }

    /**
     * Atomar balans operatsiyasi: user qatori bloklanadi, balans yangilanadi,
     * ledger yozuvi yoziladi. `$signedAmount` musbat = credit, manfiy = debit.
     */
    private function record(
        User $user,
        float $signedAmount,
        WalletTransactionType $type,
        ?string $description,
        ?Order $order,
        ?User $createdBy,
        array $meta,
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $signedAmount, $type, $description, $order, $createdBy, $meta) {
            /** @var User $locked */
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $newBalance = (float) $locked->balance + $signedAmount;

            if ($newBalance < 0) {
                throw new RuntimeException('insufficient_balance');
            }

            $locked->balance = $newBalance;
            $locked->save();

            $txn = WalletTransaction::create([
                'user_id' => $locked->id,
                'type' => $type->value,
                'amount' => $signedAmount,
                'balance_after' => $newBalance,
                'currency_code' => 'UZS',
                'description' => $description,
                'order_id' => $order?->id,
                'status' => 'completed',
                'meta' => $meta ?: null,
                'created_by' => $createdBy?->id,
            ]);

            $user->balance = $newBalance;

            return $txn;
        });
    }
}
