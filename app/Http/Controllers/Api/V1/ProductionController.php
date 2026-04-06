<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreProductionRequest;
use App\Http\Requests\UpdateProductionRequest;
use App\Http\Resources\ProductionResource;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\Shop;
use App\Services\ProductionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionController extends BaseShopController
{
    public function __construct(
        private readonly ProductionService $productionService,
    ) {}

    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $query = $shop->productions()
            ->with([
                'breadCategory',
                'recipe.breadCategory',
                'recipe.measurementUnit',
                'recipe.recipeIngredients.ingredient.measurementUnit',
                'recipe.recipeIngredients.ingredient.currency',
            ])
            ->getQuery();

        if ($request->has('date')) {
            $query->whereDate('date', $request->query('date'));
        }

        $this->applySorting($query, $request, 'date', 'desc');

        $requestObj = $request;

        if ($request->boolean('paginate', false)) {
            $perPage = min((int) $request->query('per_page', 20), 100);
            $paginator = $query->paginate($perPage);
            $collection = $paginator->getCollection();
            $financials = $this->productionService->financialsForProductions(
                $shop,
                $collection,
                $request->query('date')
            );
            $mapped = $collection->map(function ($p) use ($financials, $requestObj) {
                return array_merge(
                    (new ProductionResource($p))->toArray($requestObj),
                    $financials[$p->id] ?? [
                        'returns_amount' => 0.0,
                        'returns_quantity_allocated' => 0,
                        'gross_revenue' => 0.0,
                        'net_revenue' => 0.0,
                        'net_profit' => 0.0,
                    ]
                );
            })->values();
            $paginator->setCollection($mapped);

            return $this->paginated($paginator);
        }

        $collection = $query->get();
        $financials = $this->productionService->financialsForProductions(
            $shop,
            $collection,
            $request->query('date')
        );

        return $this->success([
            'productions' => $collection->map(function ($p) use ($financials, $requestObj) {
                return array_merge(
                    (new ProductionResource($p))->toArray($requestObj),
                    $financials[$p->id] ?? [
                        'returns_amount' => 0.0,
                        'returns_quantity_allocated' => 0,
                        'gross_revenue' => 0.0,
                        'net_revenue' => 0.0,
                        'net_profit' => 0.0,
                    ]
                );
            })->values(),
        ]);
    }

    public function store(StoreProductionRequest $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validated();
        $recipe = Recipe::with('recipeIngredients.ingredient')->findOrFail($data['recipe_id']);

        $batchCount = (float) $data['batch_count'];
        $ingredientCost = $this->productionService->calculateIngredientCost($recipe, $batchCount);
        $breadProduced = $this->productionService->calculateBreadOutput($recipe, $batchCount);
        $flourUsed = $this->productionService->calculateFlourUsed($recipe, $batchCount);

        $production = $shop->productions()->create([
            'recipe_id' => $recipe->id,
            'bread_category_id' => $data['bread_category_id'],
            'date' => $data['date'],
            'batch_count' => $batchCount,
            'flour_used_kg' => $flourUsed,
            'bread_produced' => $breadProduced,
            'ingredient_cost' => $ingredientCost,
            'created_by' => $request->user()->id,
        ]);

        $production->load(['breadCategory', 'recipe.breadCategory', 'recipe.measurementUnit']);

        return $this->created([
            'production' => new ProductionResource($production),
        ]);
    }

    public function update(UpdateProductionRequest $request, Shop $shop, Production $production): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        if ($production->shop_id !== $shop->id) {
            abort(404);
        }

        $recipe = Recipe::with('recipeIngredients.ingredient')->findOrFail($production->recipe_id);

        $batchCount = (float) $request->validated()['batch_count'];
        $ingredientCost = $this->productionService->calculateIngredientCost($recipe, $batchCount);
        $breadProduced = $this->productionService->calculateBreadOutput($recipe, $batchCount);
        $flourUsed = $this->productionService->calculateFlourUsed($recipe, $batchCount);

        $production->update([
            'batch_count' => $batchCount,
            'flour_used_kg' => $flourUsed,
            'bread_produced' => $breadProduced,
            'ingredient_cost' => $ingredientCost,
        ]);

        $production->refresh();
        $production->load([
            'breadCategory',
            'recipe.breadCategory',
            'recipe.measurementUnit',
            'recipe.recipeIngredients.ingredient.measurementUnit',
            'recipe.recipeIngredients.ingredient.currency',
        ]);

        $financials = $this->productionService->financialsForProductions(
            $shop,
            collect([$production]),
            $production->date->toDateString()
        );

        $payload = array_merge(
            (new ProductionResource($production))->toArray($request),
            $financials[$production->id] ?? [
                'returns_amount' => 0.0,
                'returns_quantity_allocated' => 0,
                'gross_revenue' => 0.0,
                'net_revenue' => 0.0,
                'net_profit' => 0.0,
            ],
        );

        return $this->success([
            'production' => $payload,
        ]);
    }

    public function destroy(Request $request, Shop $shop, Production $production): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        if ($production->shop_id !== $shop->id) {
            abort(404);
        }

        $production->delete();

        return $this->deleted();
    }
}
