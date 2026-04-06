<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'measurement_unit_id' => [
                'sometimes',
                'uuid',
                Rule::exists('measurement_units', 'id')->where(function ($q) {
                    $q->where('type', 'ingredient')
                        ->whereIn('code', StoreIngredientRequest::INGREDIENT_UNIT_CODES);
                }),
            ],
            'is_flour' => ['sometimes', 'boolean'],
            'price_per_unit' => ['sometimes', 'numeric', 'min:0'],
            'currency_id' => ['sometimes', 'nullable', 'uuid', 'exists:currencies,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
