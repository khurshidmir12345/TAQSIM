<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends BaseShopController
{
    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $query = $shop->expenses()->getQuery();

        if ($request->has('date')) {
            $query->whereDate('date', $request->query('date'));
        }

        $this->applyFilters($query, $request, ['category']);
        $this->applySorting($query, $request, 'date', 'desc');

        if ($request->boolean('paginate', false)) {
            $perPage = min((int) $request->query('per_page', 20), 100);
            $paginator = $query->paginate($perPage);

            return $this->paginated(
                ExpenseResource::collection($paginator)->resource
            );
        }

        return $this->success([
            'expenses' => ExpenseResource::collection($query->get()),
        ]);
    }

    public function store(StoreExpenseRequest $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $expense = $shop->expenses()->create($data);

        return $this->created([
            'expense' => new ExpenseResource($expense),
        ]);
    }

    public function show(Request $request, Shop $shop, Expense $expense): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        return $this->success([
            'expense' => new ExpenseResource($expense),
        ]);
    }

    public function update(UpdateExpenseRequest $request, Shop $shop, Expense $expense): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $expense->update($request->validated());

        return $this->success([
            'expense' => new ExpenseResource($expense->fresh()),
        ], __('api.updated'));
    }

    public function destroy(Request $request, Shop $shop, Expense $expense): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $expense->delete();

        return $this->deleted();
    }
}
