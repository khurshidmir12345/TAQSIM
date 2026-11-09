<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\IngredientMovementType;
use App\Http\Resources\IngredientMovementResource;
use App\Http\Resources\IngredientResource;
use App\Models\Ingredient;
use App\Models\IngredientMovement;
use App\Models\Shop;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Xom ashyo ombori: qoldiqlar, kirim, sanash va harakatlar tarixi.
 */
class InventoryController extends BaseShopController
{
    public function __construct(
        private readonly InventoryService $inventory,
    ) {}

    /** Qoldiqlar ro'yxati va ombor jami qiymati. */
    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $ingredients = $shop->ingredients()
            ->with(['currency', 'measurementUnit'])
            ->orderBy('name')
            ->get();

        $value = $ingredients->sum(
            fn (Ingredient $i) => max(0, (float) $i->stock_quantity) * (float) $i->price_per_unit
        );

        return $this->success([
            'started_at' => $shop->inventory_started_at,
            'total_value' => round($value, 2),
            'low_count' => $ingredients->filter(fn (Ingredient $i) => (float) $i->stock_quantity <= 0)->count(),
            'ingredients' => IngredientResource::collection($ingredients),
        ]);
    }

    /** Harakatlar tarixi: tur, xom ashyo, qidiruv va sana bo'yicha. */
    public function movements(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validate([
            'type' => ['nullable', Rule::enum(IngredientMovementType::class)],
            'ingredient_id' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = IngredientMovement::query()
            ->where('shop_id', $shop->id)
            ->with(['ingredient.measurementUnit', 'creator:id,name'])
            ->when($data['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($data['ingredient_id'] ?? null, fn ($q, $id) => $q->where('ingredient_id', $id))
            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($data['to'] ?? null, fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->when(trim($data['search'] ?? '') !== '', function ($q) use ($data) {
                $term = '%' . trim($data['search']) . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('note', 'like', $term)
                        ->orWhereHas('ingredient', fn ($i) => $i->withTrashed()->where('name', 'like', $term));
                });
            })
            ->orderByDesc('date')
            ->orderByDesc('created_at');

        $paginator = $query->paginate($data['per_page'] ?? 30);
        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn ($m) => (new IngredientMovementResource($m))->toArray($request)
            )
        );

        return $this->paginated($paginator);
    }

    public function storePurchase(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validate([
            'ingredient_id' => ['required', 'uuid'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:100000000000'],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $ingredient = $this->shopIngredient($shop, $data['ingredient_id']);
        $movement = $this->inventory->purchase(
            $shop,
            $ingredient,
            (float) $data['quantity'],
            (float) $data['total_amount'],
            $data['date'] ?? now()->toDateString(),
            $request->user()->id,
            $data['note'] ?? null,
        );

        return $this->created($this->payload($request, $movement, $ingredient));
    }

    public function storeAdjustment(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validate([
            'ingredient_id' => ['required', 'uuid'],
            'actual_quantity' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $ingredient = $this->shopIngredient($shop, $data['ingredient_id']);
        $movement = $this->inventory->adjust(
            $shop,
            $ingredient,
            (float) $data['actual_quantity'],
            $request->user()->id,
            $data['note'] ?? null,
        );

        return $this->success($this->payload($request, $movement, $ingredient));
    }

    public function destroyMovement(Request $request, Shop $shop, IngredientMovement $movement): JsonResponse
    {
        $this->authorizeShop($request, $shop);
        abort_if($movement->shop_id !== $shop->id, 404);

        $this->inventory->deleteMovement($movement);

        return $this->deleted();
    }

    private function shopIngredient(Shop $shop, string $id): Ingredient
    {
        return $shop->ingredients()->whereKey($id)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, ?IngredientMovement $movement, Ingredient $ingredient): array
    {
        $movement?->load(['ingredient.measurementUnit', 'creator:id,name']);

        return [
            'movement' => $movement ? (new IngredientMovementResource($movement))->toArray($request) : null,
            'ingredient' => new IngredientResource($ingredient->fresh(['currency', 'measurementUnit'])),
        ];
    }
}
