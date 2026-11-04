<?php

namespace App\Http\Requests;

use App\Models\MeasurementUnit;
use App\Models\Recipe;
use App\Http\Requests\Concerns\ValidatesRecipeOutputs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecipeRequest extends FormRequest
{
    use ValidatesRecipeOutputs;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shop = $this->route('shop');
        $isMix = $this->input('kind') === Recipe::KIND_MIX;

        return [
            // single — bitta mahsulot retsepti (eski ilova ham shu); mix — aralash partiya.
            'kind' => ['sometimes', Rule::in([Recipe::KIND_SINGLE, Recipe::KIND_MIX])],
            // Aralash partiyada asosiy mahsulot yo'q — mahsulotlar `outputs` da.
            'bread_category_id' => [
                $isMix ? 'prohibited' : 'required',
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
            'name' => ['required', 'string', 'max:255'],
            'output_quantity' => [$isMix ? 'nullable' : 'required', 'integer', 'min:1'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.ingredient_id' => ['required', 'uuid', 'exists:ingredients,id'],
            ...($isMix ? $this->mixOutputRules($shop, required: true) : ['outputs' => ['prohibited']]),
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
