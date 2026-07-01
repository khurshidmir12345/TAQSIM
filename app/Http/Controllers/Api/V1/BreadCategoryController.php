<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreBreadCategoryRequest;
use App\Http\Requests\UpdateBreadCategoryRequest;
use App\Http\Resources\BreadCategoryResource;
use App\Models\BreadCategory;
use App\Models\Currency;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BreadCategoryController extends BaseShopController
{
    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $query = $shop->breadCategories()->getQuery()->with(['currency', 'measurementUnit']);

        $this->applyFilters($query, $request, ['is_active']);
        $this->applySorting($query, $request, 'sort_order', 'asc');

        if ($request->boolean('paginate', false)) {
            $perPage = min((int) $request->query('per_page', 20), 100);
            $paginator = $query->paginate($perPage);

            return $this->paginated(
                BreadCategoryResource::collection($paginator)->resource
            );
        }

        return $this->success([
            'bread_categories' => BreadCategoryResource::collection($query->get()),
        ]);
    }

    public function store(StoreBreadCategoryRequest $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validated();
        if (empty($data['currency_id'])) {
            $data['currency_id'] = $shop->currency_id
                ?? Currency::query()->where('code', 'UZS')->value('id');
        }
        if (empty($data['measurement_unit_id'])) {
            $data['measurement_unit_id'] = \App\Models\MeasurementUnit::query()
                ->where('type', 'ingredient')
                ->where('code', 'ta')
                ->value('id');
        }

        $category = $shop->breadCategories()->create($data);
        $category->load(['currency', 'measurementUnit']);

        return $this->created([
            'bread_category' => new BreadCategoryResource($category),
        ]);
    }

    public function show(Request $request, Shop $shop, BreadCategory $breadCategory): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $breadCategory->loadMissing(['currency', 'measurementUnit']);

        return $this->success([
            'bread_category' => new BreadCategoryResource($breadCategory),
        ]);
    }

    public function update(UpdateBreadCategoryRequest $request, Shop $shop, BreadCategory $breadCategory): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $breadCategory->update($request->validated());

        return $this->success([
            'bread_category' => new BreadCategoryResource($breadCategory->fresh(['currency', 'measurementUnit'])),
        ], __('api.updated'));
    }

    public function destroy(Request $request, Shop $shop, BreadCategory $breadCategory): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $breadCategory->delete();

        return $this->deleted();
    }
}
