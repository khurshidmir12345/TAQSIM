<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIngredientRequest extends FormRequest
{
    /** Ingredient o‘lchov kodlari — `measurement_units` (ingredient) bilan mos. */
    public const INGREDIENT_UNIT_CODES = ['kg', 'g', 'l', 'ml', 'm', 'ta'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'measurement_unit_id' => [
                'required',
                'uuid',
                Rule::exists('measurement_units', 'id')->where(function ($q) {
                    $q->where('type', 'ingredient')
                        ->whereIn('code', self::INGREDIENT_UNIT_CODES);
                }),
            ],
            'is_flour' => ['sometimes', 'boolean'],
            'price_per_unit' => ['required', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'uuid', 'exists:currencies,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
