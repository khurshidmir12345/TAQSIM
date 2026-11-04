<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecipeController extends BaseShopController
{
    private const RELATIONS = ['breadCategory', 'measurementUnit', 'outputs.breadCategory', 'recipeIngredients.ingredient'];

    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $recipes = $shop->recipes()
            ->with(self::RELATIONS)
            // Yangi qo'shilgani tepada (ilgari nom bo'yicha — yangisi pastda qolardi).
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return $this->success([
            'recipes' => RecipeResource::collection($recipes),
        ]);
    }

    public function store(StoreRecipeRequest $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validated();

        $uniqueIngredients = collect($data['ingredients'])
            ->keyBy('ingredient_id')
            ->values()
            ->toArray();
        $data['ingredients'] = $uniqueIngredients;

        $isMix = ($data['kind'] ?? Recipe::KIND_SINGLE) === Recipe::KIND_MIX;
        $outputs = $this->normalizeOutputs($data);
        // Aralash partiyada asosiy mahsulot yo'q; output_quantity — jami chiqim.
        $data['bread_category_id'] = $isMix ? null : $outputs[0]['bread_category_id'];
        $data['output_quantity'] = array_sum(array_column($outputs, 'quantity'));

        $recipe = DB::transaction(function () use ($shop, $data, $outputs, $isMix) {
            // Agar shu `bread_category_id` uchun ilgari soft-deleted retsept bor
            // bo'lsa — uni butunlay tozalaymiz. Shunda DB'da orfan yozuvlar
            // qolib ketmaydi va kelajakdagi tekshiruvlar toza bo'ladi.
            if (! $isMix) {
                $this->purgeTrashedRecipesFor(
                    shopId: $shop->id,
                    breadCategoryId: $data['bread_category_id'],
                );
            }

            $recipe = $shop->recipes()->create([
                'kind' => $isMix ? Recipe::KIND_MIX : Recipe::KIND_SINGLE,
                'name' => $data['name'],
                'bread_category_id' => $data['bread_category_id'],
                'measurement_unit_id' => $data['measurement_unit_id'],
                'output_quantity' => $data['output_quantity'],
            ]);

            $this->syncOutputs($recipe, $outputs);

            foreach ($data['ingredients'] as $item) {
                $recipe->recipeIngredients()->create([
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return $recipe;
        });

        $recipe->load(self::RELATIONS);

        return $this->created([
            'recipe' => new RecipeResource($recipe),
        ]);
    }

    public function show(Request $request, Shop $shop, Recipe $recipe): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $recipe->load(self::RELATIONS);

        return $this->success([
            'recipe' => new RecipeResource($recipe),
        ]);
    }

    public function update(UpdateRecipeRequest $request, Shop $shop, Recipe $recipe): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validated();

        $outputs = null;
        if ($recipe->isMix()) {
            // Aralash partiya: mahsulotlar faqat `outputs` orqali o'zgaradi.
            // Eski ilova yuborgan output_quantity e'tiborsiz — taqsimot buzilmasin.
            unset($data['output_quantity']);
            if (isset($data['outputs'])) {
                $outputs = $this->normalizeOutputs($data);
                $data['output_quantity'] = array_sum(array_column($outputs, 'quantity'));
            }
        } elseif (isset($data['bread_category_id']) || isset($data['output_quantity'])) {
            $outputs = [[
                'bread_category_id' => $data['bread_category_id'] ?? $recipe->bread_category_id,
                'quantity' => (int) ($data['output_quantity'] ?? $recipe->output_quantity),
            ]];
        }

        if (isset($data['bread_category_id']) && $data['bread_category_id'] !== $recipe->bread_category_id) {
            $this->purgeTrashedRecipesFor($shop->id, $data['bread_category_id']);
        }

        DB::transaction(function () use ($recipe, $data, $outputs) {
            $recipe->update(collect($data)->except(['ingredients', 'outputs'])->toArray());

            if ($outputs !== null) {
                $this->syncOutputs($recipe, $outputs);
            }

            if (isset($data['ingredients'])) {
                $recipe->recipeIngredients()->delete();
                foreach ($data['ingredients'] as $item) {
                    $recipe->recipeIngredients()->create([
                        'ingredient_id' => $item['ingredient_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }
            }
        });

        $recipe->load(self::RELATIONS);

        return $this->success([
            'recipe' => new RecipeResource($recipe),
        ], __('api.updated'));
    }

    public function destroy(Request $request, Shop $shop, Recipe $recipe): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $recipe->delete();

        return $this->deleted();
    }

    /**
     * `outputs` yoki eski `bread_category_id` + `output_quantity` dan
     * yagona ro'yxat. Birinchisi — asosiy mahsulot.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{bread_category_id: string, quantity: int}>
     */
    private function normalizeOutputs(array $data): array
    {
        if (! empty($data['outputs'])) {
            return array_values(array_map(fn (array $o) => [
                'bread_category_id' => (string) $o['bread_category_id'],
                'quantity' => (int) $o['quantity'],
            ], $data['outputs']));
        }

        return [[
            'bread_category_id' => (string) $data['bread_category_id'],
            'quantity' => (int) $data['output_quantity'],
        ]];
    }

    /** @param  list<array{bread_category_id: string, quantity: int}>  $outputs */
    private function syncOutputs(Recipe $recipe, array $outputs): void
    {
        $recipe->outputs()->delete();
        foreach ($outputs as $i => $o) {
            $recipe->outputs()->create($o + ['sort_order' => $i]);
        }
        $recipe->unsetRelation('outputs');
    }

    /**
     * Belgilangan do'kon va mahsulot turi bo'yicha soft-deleted retseptlarni
     * (va ularga tegishli `recipe_ingredients` yozuvlarini) butunlay o'chiradi.
     *
     * Foydalanuvchi retseptni o'chirib, o'sha `bread_category_id` uchun qayta
     * yaratganida DB toza qolishi uchun chaqiriladi.
     */
    private function purgeTrashedRecipesFor(string $shopId, string $breadCategoryId): void
    {
        $trashed = Recipe::onlyTrashed()
            ->where('shop_id', $shopId)
            ->where('bread_category_id', $breadCategoryId)
            ->get();

        foreach ($trashed as $old) {
            $old->recipeIngredients()->delete();
            $old->forceDelete();
        }
    }
}
