<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends BaseShopController
{
    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $query = $shop->customers()->getQuery();

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('phone', 'like', $search);
            });
        }

        $this->applySorting($query, $request, 'name', 'asc');

        if ($request->boolean('paginate', false)) {
            $perPage = min((int) $request->query('per_page', 20), 100);
            $paginator = $query->paginate($perPage);

            return $this->paginated(
                CustomerResource::collection($paginator)->resource
            );
        }

        return $this->success([
            'customers' => CustomerResource::collection($query->get()),
        ]);
    }

    public function store(StoreCustomerRequest $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $customer = $shop->customers()->create($data);

        return $this->created([
            'customer' => new CustomerResource($customer),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Shop $shop, Customer $customer): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        if ($customer->shop_id !== $shop->id) {
            abort(404);
        }

        $customer->update($request->validated());

        return $this->success([
            'customer' => new CustomerResource($customer->fresh()),
        ], __('api.updated'));
    }

    public function destroy(Request $request, Shop $shop, Customer $customer): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        if ($customer->shop_id !== $shop->id) {
            abort(404);
        }

        if ($customer->orders()->exists()) {
            return $this->error(__('api.errors.customer_has_orders'), 422);
        }

        $customer->delete();

        return $this->deleted();
    }
}
