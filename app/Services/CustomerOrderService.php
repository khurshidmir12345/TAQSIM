<?php

namespace App\Services;

use App\Enums\CustomerOrderStatus;
use App\Models\BreadCategory;
use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderPayment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CustomerOrderService
{
    public function createOrder(Shop $shop, User $user, array $data): CustomerOrder
    {
        return DB::transaction(function () use ($shop, $user, $data) {
            $customer = $this->resolveCustomer($shop, $user, $data);
            $prepared = $this->prepareItems($shop, $data['items']);

            $order = $shop->customerOrders()->create([
                'customer_id' => $customer->id,
                'status' => CustomerOrderStatus::Active,
                'delivery_date' => $data['delivery_date'],
                'delivery_time' => $data['delivery_time'] ?? null,
                'total_amount' => $prepared['total_amount'],
                'note' => $data['note'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->syncItems($order, $prepared['items']);

            if (! empty($data['advance_amount']) && (float) $data['advance_amount'] > 0) {
                if ((float) $data['advance_amount'] > $prepared['total_amount']) {
                    throw new RuntimeException('payment_exceeds_remaining');
                }

                $this->createPayment(
                    $shop,
                    $order,
                    $user,
                    (float) $data['advance_amount'],
                    $data['advance_paid_at'] ?? null,
                    $data['advance_note'] ?? null,
                );
            }

            return $this->loadOrderDetail($order);
        });
    }

    public function updateOrder(Shop $shop, CustomerOrder $order, array $data): CustomerOrder
    {
        return DB::transaction(function () use ($shop, $order, $data) {
            $order = $this->lockOrderForUpdate($order->id);
            $this->assertBelongsToShop($order, $shop);
            $this->assertActive($order);

            $updates = [];

            if (array_key_exists('delivery_date', $data)) {
                $updates['delivery_date'] = $data['delivery_date'];
            }

            if (array_key_exists('delivery_time', $data)) {
                $updates['delivery_time'] = $data['delivery_time'];
            }

            if (array_key_exists('note', $data)) {
                $updates['note'] = $data['note'];
            }

            if (! empty($data['items'])) {
                $prepared = $this->prepareItems($shop, $data['items']);
                $paidAmount = $this->paidAmount($order);

                if ($prepared['total_amount'] < $paidAmount) {
                    throw new RuntimeException('order_total_below_paid');
                }

                $updates['total_amount'] = $prepared['total_amount'];
                $order->items()->delete();
                $this->syncItems($order, $prepared['items']);
            }

            if ($updates !== []) {
                $order->update($updates);
            }

            return $this->loadOrderDetail($order->fresh());
        });
    }

    public function addPayment(
        Shop $shop,
        CustomerOrder $order,
        User $user,
        float $amount,
        ?string $paidAt = null,
        ?string $note = null,
    ): CustomerOrderPayment {
        return DB::transaction(function () use ($shop, $order, $user, $amount, $paidAt, $note) {
            $order = $this->lockOrderForUpdate($order->id);
            $this->assertBelongsToShop($order, $shop);
            $this->assertActive($order);

            if ($amount <= 0) {
                throw new RuntimeException('payment_amount_invalid');
            }

            $remaining = $this->remainingAmount($order);

            if ($amount > $remaining) {
                throw new RuntimeException('payment_exceeds_remaining');
            }

            return $this->createPayment($shop, $order, $user, $amount, $paidAt, $note);
        });
    }

    public function deliver(
        Shop $shop,
        CustomerOrder $order,
        User $user,
        ?float $paymentAmount = null,
        ?string $paymentNote = null,
    ): CustomerOrder {
        return DB::transaction(function () use ($shop, $order, $user, $paymentAmount, $paymentNote) {
            $order = $this->lockOrderForUpdate($order->id);
            $this->assertBelongsToShop($order, $shop);
            $this->assertActive($order);

            if ($paymentAmount !== null && $paymentAmount > 0) {
                if ($paymentAmount > $this->remainingAmount($order)) {
                    throw new RuntimeException('payment_exceeds_remaining');
                }

                $this->createPayment($shop, $order, $user, $paymentAmount, null, $paymentNote);
            }

            $order->update([
                'status' => CustomerOrderStatus::Delivered,
                'delivered_at' => now(),
            ]);

            return $this->loadOrderDetail($order->fresh());
        });
    }

    public function cancel(Shop $shop, CustomerOrder $order): CustomerOrder
    {
        return DB::transaction(function () use ($shop, $order) {
            $order = $this->lockOrderForUpdate($order->id);
            $this->assertBelongsToShop($order, $shop);
            $this->assertActive($order);

            $order->update([
                'status' => CustomerOrderStatus::Cancelled,
            ]);

            return $this->loadOrderDetail($order->fresh());
        });
    }

    public function deleteOrder(Shop $shop, CustomerOrder $order): void
    {
        DB::transaction(function () use ($shop, $order) {
            $order = $this->lockOrderForUpdate($order->id);
            $this->assertBelongsToShop($order, $shop);
            $this->assertActive($order);

            if ($order->payments()->exists()) {
                throw new RuntimeException('order_has_payments');
            }

            $order->delete();
        });
    }

    /** @return array{items: array<int, array<string, mixed>>, total_amount: float} */
    public function prepareItems(Shop $shop, array $items): array
    {
        $categoryIds = collect($items)->pluck('bread_category_id')->unique()->all();
        $categories = BreadCategory::query()
            ->where('shop_id', $shop->id)
            ->whereIn('id', $categoryIds)
            ->get()
            ->keyBy('id');

        if ($categories->count() !== count($categoryIds)) {
            throw new RuntimeException('bread_category_not_in_shop');
        }

        $preparedItems = [];
        $totalAmount = 0.0;

        foreach ($items as $item) {
            $category = $categories->get($item['bread_category_id']);
            $quantity = (int) $item['quantity'];
            $unitPrice = array_key_exists('unit_price', $item) && $item['unit_price'] !== null
                ? round((float) $item['unit_price'], 2)
                : round((float) $category->selling_price, 2);
            $subtotal = round($quantity * $unitPrice, 2);
            $totalAmount += $subtotal;

            $preparedItems[] = [
                'bread_category_id' => $category->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ];
        }

        return [
            'items' => $preparedItems,
            'total_amount' => round($totalAmount, 2),
        ];
    }

    public function paidAmount(CustomerOrder $order): float
    {
        return round((float) $order->payments()->sum('amount'), 2);
    }

    public function remainingAmount(CustomerOrder $order): float
    {
        return round(max(0, (float) $order->total_amount - $this->paidAmount($order)), 2);
    }

    public function loadOrderDetail(CustomerOrder $order): CustomerOrder
    {
        return $order->load([
            'customer',
            'items.breadCategory',
            'payments',
        ]);
    }

    public function assertBelongsToShop(CustomerOrder $order, Shop $shop): void
    {
        if ($order->shop_id !== $shop->id) {
            abort(404);
        }
    }

    public function assertActive(CustomerOrder $order): void
    {
        if ($order->status !== CustomerOrderStatus::Active) {
            throw new RuntimeException('order_not_active');
        }
    }

    private function lockOrderForUpdate(string $orderId): CustomerOrder
    {
        return CustomerOrder::query()
            ->whereKey($orderId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function resolveCustomer(Shop $shop, User $user, array $data): Customer
    {
        if (! empty($data['customer_id'])) {
            $customer = Customer::query()->findOrFail($data['customer_id']);

            if ($customer->shop_id !== $shop->id) {
                abort(404);
            }

            return $customer;
        }

        $inline = $data['customer'];

        return $shop->customers()->create([
            'name' => $inline['name'],
            'phone' => $inline['phone'] ?? null,
            'note' => $inline['note'] ?? null,
            'created_by' => $user->id,
        ]);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function syncItems(CustomerOrder $order, array $items): void
    {
        foreach ($items as $item) {
            $order->items()->create($item);
        }
    }

    private function createPayment(
        Shop $shop,
        CustomerOrder $order,
        User $user,
        float $amount,
        ?string $paidAt = null,
        ?string $note = null,
    ): CustomerOrderPayment {
        return $order->payments()->create([
            'shop_id' => $shop->id,
            'amount' => round($amount, 2),
            'paid_at' => $paidAt ?? now(),
            'note' => $note,
            'created_by' => $user->id,
        ]);
    }
}
