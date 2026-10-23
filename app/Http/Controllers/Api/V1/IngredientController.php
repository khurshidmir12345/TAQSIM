<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreIngredientRequest;
use App\Http\Requests\UpdateIngredientRequest;
use App\Http\Resources\IngredientResource;
use App\Models\Currency;
use App\Models\Ingredient;
use App\Models\MeasurementUnit;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IngredientController extends BaseShopController
{
    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $query = $shop->ingredients()->getQuery()->with(['currency', 'measurementUnit']);

        $this->applyFilters($query, $request, ['is_active', 'is_flour']);
        // Yangi qo'shilgani tepada. Bir soniyada qo'shilganlar (masalan namuna
        // katalog) uchun id ham — UUID v7 vaqt bo'yicha o'sadi.
        $this->applySorting($query, $request, 'created_at', 'desc');
        $query->orderByDesc('id');

        if ($request->boolean('paginate', false)) {
            $perPage = min((int) $request->query('per_page', 20), 100);
            $paginator = $query->paginate($perPage);

            return $this->paginated(
                IngredientResource::collection($paginator)->resource
            );
        }

        return $this->success([
            'ingredients' => IngredientResource::collection($query->get()),
        ]);
    }

    public function store(StoreIngredientRequest $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validated();
        if (empty($data['currency_id'])) {
            $data['currency_id'] = $shop->currency_id
                ?? Currency::query()->where('code', 'UZS')->value('id');
        }

        $mu = MeasurementUnit::query()->findOrFail($data['measurement_unit_id']);
        $data['unit'] = Ingredient::unitFromMeasurementCode($mu->code);

        $ingredient = $shop->ingredients()->create($data);
        $ingredient->load(['currency', 'measurementUnit']);

        return $this->created([
            'ingredient' => new IngredientResource($ingredient),
        ]);
    }

    public function show(Request $request, Shop $shop, Ingredient $ingredient): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $ingredient->loadMissing(['currency', 'measurementUnit']);

        return $this->success([
            'ingredient' => new IngredientResource($ingredient),
        ]);
    }

    public function update(UpdateIngredientRequest $request, Shop $shop, Ingredient $ingredient): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validated();
        if (isset($data['measurement_unit_id'])) {
            $mu = MeasurementUnit::query()->findOrFail($data['measurement_unit_id']);
            $data['unit'] = Ingredient::unitFromMeasurementCode($mu->code);
        }

        $ingredient->update($data);

        return $this->success([
            'ingredient' => new IngredientResource($ingredient->fresh(['currency', 'measurementUnit'])),
        ], __('api.updated'));
    }

    public function destroy(Request $request, Shop $shop, Ingredient $ingredient): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $ingredient->delete();

        return $this->deleted();
    }
}
