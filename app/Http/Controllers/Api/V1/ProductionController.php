<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreProductionRequest;
use App\Http\Requests\UpdateProductionRequest;
use App\Http\Resources\ProductionResource;
use App\Models\BreadCategory;
use App\Models\Production;
use App\Models\Recipe;
use App\Models\Shop;
use App\Services\ProductionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProductionController extends BaseShopController
{
    private const RELATIONS = [
        'breadCategory',
        'recipe.breadCategory',
        'recipe.measurementUnit',
        'recipe.outputs.breadCategory',
        'recipe.recipeIngredients.ingredient.measurementUnit',
        'recipe.recipeIngredients.ingredient.currency',
    ];

    private const EMPTY_FINANCIALS = [
        'returns_amount' => 0.0,
        'returns_quantity_allocated' => 0,
        'gross_revenue' => 0.0,
        'net_revenue' => 0.0,
        'net_profit' => 0.0,
    ];

    public function __construct(
        private readonly ProductionService $productionService,
    ) {}

    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $query = $shop->productions()->with(self::RELATIONS)->getQuery();

        if ($request->has('date')) {
            $query->whereDate('date', $request->query('date'));
        }

        $this->applySorting($query, $request, 'date', 'desc');
        // Bir partiyaning qatorlari ro'yxatda (va sahifalashda) yonma-yon tursin.
        $query->orderByDesc('created_at')->orderBy('batch_id')->orderBy('id');

        if ($request->boolean('paginate', false)) {
            $perPage = min((int) $request->query('per_page', 20), 100);
            $paginator = $query->paginate($perPage);
            $paginator->setCollection(
                $this->present($request, $shop, $paginator->getCollection(), $request->query('date'))
            );

            return $this->paginated($paginator);
        }

        return $this->success([
            'productions' => $this->present($request, $shop, $query->get(), $request->query('date')),
        ]);
    }

    public function store(StoreProductionRequest $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validated();

        if (empty($data['recipe_id'])) {
            $rows = $this->productionService->saveDirect(
                $shop,
                $this->itemsFromRequest($shop, $data['items']),
                $data['date'],
                $request->user()->id,
            );

            return $this->created($this->createdPayload($rows));
        }

        $recipe = $this->shopRecipe($shop, $data['recipe_id']);
        $batchCount = (float) $data['batch_count'];

        if (isset($data['items'])) {
            $items = $this->itemsFromRequest($shop, $data['items']);
        } elseif ($recipe->outputs->count() > 1) {
            // Eski ilova bitta mahsulot yuboradi — partiya retseptning odatiy
            // taqsimoti bo'yicha barcha mahsulotlarga yoziladi.
            $items = $this->productionService->defaultItems($recipe, $batchCount);
        } else {
            $items = [
                $data['bread_category_id'] => $this->productionService->calculateBreadOutput($recipe, $batchCount),
            ];
        }

        $rows = $this->productionService->saveBatch(
            $shop,
            $recipe,
            $batchCount,
            $items,
            $data['date'],
            $request->user()->id,
        );

        return $this->created($this->createdPayload($rows));
    }

    /**
     * @param  Collection<int, Production>  $rows
     * @return array<string, mixed>
     */
    private function createdPayload(Collection $rows): array
    {
        $rows->each->load(['breadCategory', 'recipe.breadCategory', 'recipe.measurementUnit', 'recipe.outputs.breadCategory']);

        return [
            'production' => new ProductionResource($rows->first()),
            'productions' => ProductionResource::collection($rows),
        ];
    }

    public function update(UpdateProductionRequest $request, Shop $shop, Production $production): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        if ($production->shop_id !== $shop->id) {
            abort(404);
        }

        $data = $request->validated();

        if ($production->recipe_id === null) {
            if (! isset($data['items'])) {
                throw ValidationException::withMessages([
                    'items' => __('validation.required', ['attribute' => 'items']),
                ]);
            }
            $rows = $this->productionService->saveDirect(
                $shop,
                $this->itemsFromRequest($shop, $data['items']),
                $production->date->toDateString(),
                $request->user()->id,
                $production,
            );
        } else {
            if (! isset($data['batch_count'])) {
                throw ValidationException::withMessages([
                    'batch_count' => __('validation.required', ['attribute' => 'batch_count']),
                ]);
            }
            $rows = $this->updateRecipeBatch($request, $shop, $production, $data);
        }

        $rows = Production::query()
            ->with(self::RELATIONS)
            ->whereIn('id', $rows->pluck('id'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $presented = $this->present($request, $shop, $rows, $production->date->toDateString());
        $self = $presented->firstWhere('id', $production->id) ?? $presented->first();

        return $this->success([
            'production' => $self,
            'productions' => $presented,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, Production>
     */
    private function updateRecipeBatch(Request $request, Shop $shop, Production $production, array $data): Collection
    {
        $recipe = $this->shopRecipe($shop, $production->recipe_id, withTrashed: true);
        $batchCount = (float) $data['batch_count'];
        $existing = $production->batchRows();

        if (isset($data['items'])) {
            $rows = $this->productionService->saveBatch(
                $shop,
                $recipe,
                $batchCount,
                $this->itemsFromRequest($shop, $data['items']),
                $production->date->toDateString(),
                $request->user()->id,
                $existing,
            );
        } elseif ($production->batch_id === null && $recipe->outputs->count() <= 1) {
            $rows = $this->productionService->saveBatch(
                $shop,
                $recipe,
                $batchCount,
                [$production->bread_category_id => $this->productionService->calculateBreadOutput($recipe, $batchCount)],
                $production->date->toDateString(),
                $request->user()->id,
                $existing,
            );
        } else {
            $this->productionService->rescaleRow($production, $recipe, $batchCount);
            $rows = $production->batchRows();
        }

        return $rows;
    }

    public function destroy(Request $request, Shop $shop, Production $production): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        if ($production->shop_id !== $shop->id) {
            abort(404);
        }

        // Har qator alohida o'chiriladi — observer kassadagi yozuvni ham olib tashlaydi.
        $rows = $request->boolean('whole_batch') ? $production->batchRows() : collect([$production]);
        foreach ($rows as $row) {
            $row->delete();
        }

        return $this->deleted();
    }

    private function shopRecipe(Shop $shop, string $recipeId, bool $withTrashed = false): Recipe
    {
        $query = Recipe::query()
            ->with(['recipeIngredients.ingredient', 'outputs'])
            ->where('shop_id', $shop->id);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->findOrFail($recipeId);
    }

    /**
     * @param  list<array{bread_category_id: string, quantity: int}>  $items
     * @return array<string, int>
     */
    private function itemsFromRequest(Shop $shop, array $items): array
    {
        $map = [];
        foreach ($items as $item) {
            $map[$item['bread_category_id']] = (int) $item['quantity'];
        }

        $owned = BreadCategory::query()
            ->where('shop_id', $shop->id)
            ->whereIn('id', array_keys($map))
            ->count();
        if ($owned !== count($map)) {
            throw ValidationException::withMessages([
                'items' => __('validation.exists', ['attribute' => 'items']),
            ]);
        }

        return $map;
    }

    /**
     * Resurs + moliyaviy ko'rsatkichlar + partiya jamisi.
     *
     * @param  Collection<int, Production>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function present(Request $request, Shop $shop, Collection $rows, ?string $date): Collection
    {
        $financials = $this->productionService->financialsForProductions($shop, $rows, $date);

        $batchIds = $rows->pluck('batch_id')->filter()->unique()->values();
        $totals = $batchIds->isEmpty()
            ? collect()
            : Production::query()
                ->where('shop_id', $shop->id)
                ->whereIn('batch_id', $batchIds)
                ->groupBy('batch_id')
                ->selectRaw('batch_id, SUM(batch_count) as total')
                ->pluck('total', 'batch_id');

        return $rows->map(function (Production $p) use ($request, $financials, $totals) {
            return array_merge(
                (new ProductionResource($p))->toArray($request),
                $financials[$p->id] ?? self::EMPTY_FINANCIALS,
                [
                    'batch_total_count' => $p->batch_id !== null
                        ? round((float) ($totals[$p->batch_id] ?? $p->batch_count), 4)
                        : (float) $p->batch_count,
                ],
            );
        })->values();
    }
}
