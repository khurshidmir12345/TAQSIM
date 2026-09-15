<?php

namespace App\Http\Requests;

use App\Models\MeasurementUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shop = $this->route('shop');
        $recipe = $this->route('recipe');

        return [
            'bread_category_id' => [
                'sometimes',
                'uuid',
                Rule::exists('bread_categories', 'id')->where('shop_id', $shop->id),
                // Joriy retseptni istisno qilamiz va soft-deleted yozuvlarni
                // tekshiruvdan chiqaramiz — aks holda o'chirilgan eski retsept
                // yangilashga to'siq bo'lishi mumkin.
                Rule::unique('recipes', 'bread_category_id')
                    ->where('shop_id', $shop->id)
                    ->whereNull('deleted_at')
                    ->ignore($recipe->id),
            ],
            'measurement_unit_id' => [
                'sometimes',
                'uuid',
                // Tizim partiya birliklari yoki shu do'konning o'z birligi.
                Rule::exists('measurement_units', 'id')->where(function ($q) use ($shop) {
                    $q->where('type', 'batch')->where(function ($g) use ($shop) {
                        $g->where(function ($s) {
                            $s->whereNull('shop_id')
                                ->whereIn('code', MeasurementUnit::BATCH_UNIT_CODES);
                        })->orWhere('shop_id', $shop->id);
                    });
                }),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'output_quantity' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'ingredients' => ['sometimes', 'array', 'min:1'],
            'ingredients.*.ingredient_id' => ['required_with:ingredients', 'uuid', 'exists:ingredients,id'],
            'ingredients.*.quantity' => ['required_with:ingredients', 'numeric', 'min:0.001'],
        ];
    }

    public function messages(): array
    {
        return [
            'bread_category_id.unique' => __('api.recipe.duplicate_bread_category'),
        ];
    }
}
