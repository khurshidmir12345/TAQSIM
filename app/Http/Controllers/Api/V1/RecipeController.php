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
    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $recipes = $shop->recipes()
            ->with(['breadCategory', 'measurementUnit', 'recipeIngredients.ingredient'])
            ->orderBy('name')
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

        $recipe = DB::transaction(function () use ($shop, $data) {
            // Agar shu `bread_category_id` uchun ilgari soft-deleted retsept bor
            // bo'lsa — uni butunlay tozalaymiz. Shunda DB'da orfan yozuvlar
            // qolib ketmaydi va kelajakdagi tekshiruvlar toza bo'ladi.
            $this->purgeTrashedRecipesFor(
                shopId: $shop->id,
                breadCategoryId: $data['bread_category_id'],
            );

            $recipe = $shop->recipes()->create([
                'name' => $data['name'],
                'bread_category_id' => $data['bread_category_id'],
                'measurement_unit_id' => $data['measurement_unit_id'],
                'output_quantity' => $data['output_quantity'],
            ]);

            foreach ($data['ingredients'] as $item) {
                $recipe->recipeIngredients()->create([
                    'ingredient_id' => $item['ingredient_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return $recipe;
        });

        $recipe->load(['breadCategory', 'measurementUnit', 'recipeIngredients.ingredient']);

        return $this->created([
            'recipe' => new RecipeResource($recipe),
        ]);
    }

    public function show(Request $request, Shop $shop, Recipe $recipe): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $recipe->load(['breadCategory', 'measurementUnit', 'recipeIngredients.ingredient']);

        return $this->success([
            'recipe' => new RecipeResource($recipe),
        ]);
    }

    public function update(UpdateRecipeRequest $request, Shop $shop, Recipe $recipe): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validated();

        DB::transaction(function () use ($recipe, $data) {
            $recipe->update(collect($data)->except(['ingredients'])->toArray());

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

        $recipe->load(['breadCategory', 'measurementUnit', 'recipeIngredients.ingredient']);

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
