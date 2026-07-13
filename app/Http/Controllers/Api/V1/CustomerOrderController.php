<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CustomerOrderStatus;
use App\Http\Requests\DeliverCustomerOrderRequest;
use App\Http\Requests\StoreCustomerOrderPaymentRequest;
use App\Http\Requests\StoreCustomerOrderRequest;
use App\Http\Requests\UpdateCustomerOrderRequest;
use App\Http\Resources\CustomerOrderPaymentResource;
use App\Http\Resources\CustomerOrderResource;
use App\Models\CustomerOrder;
use App\Models\Shop;
use App\Services\CustomerOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class CustomerOrderController extends BaseShopController
{
    public function __construct(
        private readonly CustomerOrderService $customerOrderService,
    ) {}

    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $query = $shop->customerOrders()
            ->with(['customer', 'items.breadCategory', 'payments'])
            ->getQuery();

        if ($request->has('date')) {
            $query->whereDate('delivery_date', $request->query('date'));
        }

        if ($request->filled('from')) {
            $query->whereDate('delivery_date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('delivery_date', '<=', $request->query('to'));
        }

        if ($request->filled('status')) {
            Validator::make($request->only('status'), [
                'status' => ['required', Rule::in(CustomerOrderStatus::values())],
            ])->validate();

            $query->where('status', $request->query('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->query('customer_id'));
        }

        $query->orderBy('delivery_date', 'asc')
            ->orderBy('delivery_time', 'asc');

        $perPage = min((int) $request->query('per_page', 20), 100);
        $paginator = $query->paginate($perPage);

        return $this->paginated(
            CustomerOrderResource::collection($paginator)->resource
        );
    }

    public function store(StoreCustomerOrderRequest $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        try {
            $order = $this->customerOrderService->createOrder(
                $shop,
                $request->user(),
                $request->validated(),
            );
        } catch (RuntimeException $exception) {
            return $this->mapServiceException($exception);
        }

        return $this->created([
            'customer_order' => new CustomerOrderResource($order),
        ]);
    }

    public function show(Request $request, Shop $shop, CustomerOrder $customerOrder): JsonResponse
    {
        $this->authorizeShop($request, $shop);
        $this->customerOrderService->assertBelongsToShop($customerOrder, $shop);

        $order = $this->customerOrderService->loadOrderDetail($customerOrder);

        return $this->success([
            'customer_order' => new CustomerOrderResource($order),
        ]);
    }

    public function update(UpdateCustomerOrderRequest $request, Shop $shop, CustomerOrder $customerOrder): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        try {
            $order = $this->customerOrderService->updateOrder(
                $shop,
                $customerOrder,
                $request->validated(),
            );
        } catch (RuntimeException $exception) {
            return $this->mapServiceException($exception);
        }

        return $this->success([
            'customer_order' => new CustomerOrderResource($order),
        ], __('api.updated'));
    }

    public function storePayment(
        StoreCustomerOrderPaymentRequest $request,
        Shop $shop,
        CustomerOrder $customerOrder,
    ): JsonResponse {
        $this->authorizeShop($request, $shop);

        $data = $request->validated();

        try {
            $payment = $this->customerOrderService->addPayment(
                $shop,
                $customerOrder,
                $request->user(),
                (float) $data['amount'],
                $data['paid_at'] ?? null,
                $data['note'] ?? null,
            );
            $order = $this->customerOrderService->loadOrderDetail($customerOrder->fresh());
        } catch (RuntimeException $exception) {
            return $this->mapServiceException($exception);
        }

        return $this->created([
            'payment' => new CustomerOrderPaymentResource($payment),
            'customer_order' => new CustomerOrderResource($order),
        ]);
    }

    public function deliver(
        DeliverCustomerOrderRequest $request,
        Shop $shop,
        CustomerOrder $customerOrder,
    ): JsonResponse {
        $this->authorizeShop($request, $shop);

        $data = $request->validated();

        try {
            $order = $this->customerOrderService->deliver(
                $shop,
                $customerOrder,
                $request->user(),
                isset($data['payment_amount']) ? (float) $data['payment_amount'] : null,
                $data['payment_note'] ?? null,
            );
        } catch (RuntimeException $exception) {
            return $this->mapServiceException($exception);
        }

        return $this->success([
            'customer_order' => new CustomerOrderResource($order),
        ], __('api.updated'));
    }

    public function cancel(Request $request, Shop $shop, CustomerOrder $customerOrder): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        try {
            $order = $this->customerOrderService->cancel($shop, $customerOrder);
        } catch (RuntimeException $exception) {
            return $this->mapServiceException($exception);
        }

        return $this->success([
            'customer_order' => new CustomerOrderResource($order),
        ], __('api.updated'));
    }

    public function destroy(Request $request, Shop $shop, CustomerOrder $customerOrder): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        try {
            $this->customerOrderService->deleteOrder($shop, $customerOrder);
        } catch (RuntimeException $exception) {
            return $this->mapServiceException($exception);
        }

        return $this->deleted();
    }

    private function mapServiceException(RuntimeException $exception): JsonResponse
    {
        return match ($exception->getMessage()) {
            'order_not_active' => $this->error(__('api.errors.order_not_active'), 422),
            'order_total_below_paid' => $this->error(__('api.errors.order_total_below_paid'), 422),
            'payment_exceeds_remaining' => $this->error(__('api.errors.payment_exceeds_remaining'), 422),
            'payment_amount_invalid' => $this->error(__('api.errors.payment_amount_invalid'), 422),
            'bread_category_not_in_shop' => $this->error(__('api.errors.bread_category_not_in_shop'), 422),
            'order_has_payments' => $this->error(__('api.errors.order_has_payments'), 422),
            default => $this->error(__('api.errors.generic'), 422),
        };
    }
}
