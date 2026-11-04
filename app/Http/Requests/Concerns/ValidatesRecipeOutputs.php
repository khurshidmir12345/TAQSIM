<?php

namespace App\Http\Requests\Concerns;

use App\Models\Shop;
use Illuminate\Validation\Rule;

/**
 * Aralash partiyaning chiqadigan mahsulotlari (`outputs`).
 *
 * Mahsulot retseptidan farqli o'laroq bu yerda cheklov yo'q: o'z retsepti bor
 * mahsulot ham, boshqa aralash partiyadagi mahsulot ham kira oladi.
 */
trait ValidatesRecipeOutputs
{
    /** @return array<string, mixed> */
    protected function mixOutputRules(Shop $shop, bool $required): array
    {
        return [
            'outputs' => [$required ? 'required' : 'sometimes', 'array', 'min:2', 'max:20'],
            'outputs.*.bread_category_id' => [
                'required_with:outputs',
                'uuid',
                'distinct',
                Rule::exists('bread_categories', 'id')->where('shop_id', $shop->id),
            ],
            'outputs.*.quantity' => ['required_with:outputs', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
