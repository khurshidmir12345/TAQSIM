<?php

namespace App\Http\Requests;

use App\Models\MeasurementUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shop = $this->route('shop');

        return [
            'bread_category_id' => [
                'required',
                'uuid',
                Rule::exists('bread_categories', 'id')->where('shop_id', $shop->id),
                // Faqat aktiv (soft-deleted bo'lmagan) retseptlar orasida takrorlanishni
                // taqiqlaymiz. Foydalanuvchi o'chirgan retsept yangi yaratishga to'siq
                // bo'lmasligi kerak.
                Rule::unique('recipes', 'bread_category_id')
                    ->where('shop_id', $shop->id)
                    ->whereNull('deleted_at'),
            ],
            'measurement_unit_id' => [
                'required',
                'uuid',
                Rule::exists('measurement_units', 'id')->where(function ($q) {
                    $q->where('type', 'batch')
                        ->whereIn('code', MeasurementUnit::BATCH_UNIT_CODES);
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'output_quantity' => ['required', 'integer', 'min:1'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.ingredient_id' => ['required', 'uuid', 'exists:ingredients,id'],
            'ingredients.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ];
    }

    public function messages(): array
    {
        return [
            'bread_category_id.unique' => __('api.recipe.duplicate_bread_category'),
        ];
    }
}
