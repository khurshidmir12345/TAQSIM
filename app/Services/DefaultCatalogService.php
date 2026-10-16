<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\MeasurementUnit;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Yangi foydalanuvchining birinchi biznesiga namunaviy mahsulot, xom ashyo
 * va retseptlar (`config/default_catalog.php`, biznes turi bo'yicha).
 *
 * Maqsad — "nimadan boshlashni bilmayman" muammosi: odam tayyor namunani
 * ko'radi va o'ziga moslab o'zgartiradi yoki o'chiradi.
 */
class DefaultCatalogService
{
    /** @return bool namuna qo'shildimi */
    public function seed(Shop $shop, string $locale): bool
    {
        $template = config('default_catalog.' . $shop->businessType?->key);
        if (! is_array($template)) {
            return false;
        }

        $currency = $shop->currency?->code;
        // Narxlar so'mda yozilgan — boshqa valyutada noto'g'ri bo'lmasin.
        $price = fn (float $uzs) => $currency === 'UZS' ? $uzs : 0;
        $name = fn (string $group, string $key) => __("default_catalog.{$group}.{$key}", [], $locale);

        $units = MeasurementUnit::query()
            ->global()
            ->whereIn('code', array_merge(
                ['ta'],
                array_column($template['ingredients'], 'unit'),
                array_column($template['products'], 'batch_unit'),
            ))
            ->pluck('id', 'code');

        try {
            DB::transaction(function () use ($shop, $template, $price, $name, $units) {
                $ingredients = [];
                $sort = 0;
                foreach ($template['ingredients'] as $key => $item) {
                    $ingredients[$key] = $shop->ingredients()->create([
                        'name' => $name('ingredients', $key),
                        'unit' => Ingredient::unitFromMeasurementCode($item['unit']),
                        'measurement_unit_id' => $units[$item['unit']] ?? null,
                        'is_flour' => $item['is_flour'] ?? false,
                        'price_per_unit' => $price($item['price']),
                        'currency_id' => $shop->currency_id,
                        'sort_order' => $sort++,
                        'is_active' => true,
                    ]);
                }

                $sort = 0;
                foreach ($template['products'] as $key => $item) {
                    $product = $shop->breadCategories()->create([
                        'name' => $name('products', $key),
                        'selling_price' => $price($item['price']),
                        'currency_id' => $shop->currency_id,
                        'measurement_unit_id' => $units['ta'] ?? null,
                        'sort_order' => $sort++,
                        'is_active' => true,
                    ]);

                    $recipe = $shop->recipes()->create([
                        'bread_category_id' => $product->id,
                        'name' => $product->name,
                        'measurement_unit_id' => $units[$item['batch_unit']] ?? null,
                        'output_quantity' => $item['output'],
                        'is_active' => true,
                    ]);

                    foreach ($item['recipe'] as $ingredientKey => $quantity) {
                        $recipe->recipeIngredients()->create([
                            'ingredient_id' => $ingredients[$ingredientKey]->id,
                            'quantity' => $quantity,
                        ]);
                    }
                }

                // Ishlatilgan birliklar do'kon birliklari ro'yxatida ham bo'lsin.
                $shop->measurementUnits()->syncWithoutDetaching($units->values()->all());
            });
        } catch (\Throwable $e) {
            // Namuna — qulaylik; xato bo'lsa do'kon yaratilishi to'xtamasin.
            Log::warning('[default-catalog] Namuna qo\'shilmadi', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}
