<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Retseptsiz chiqimda NULL — unda faqat `items` (mahsulot va soni).
            'recipe_id' => ['nullable', 'uuid', 'exists:recipes,id'],
            // Eski ilova: bitta mahsulot. Yangi ilova: `items` (bir partiyadan
            // bir nechta mahsulot) — bunda bread_category_id shart emas.
            'bread_category_id' => ['required_without:items', 'uuid', 'exists:bread_categories,id'],
            'date' => ['required', 'date'],
            'batch_count' => ['required_with:recipe_id', 'nullable', 'numeric', 'min:0.1'],
            'items' => ['required_without:recipe_id', 'array', 'min:1', 'max:30'],
            'items.*.bread_category_id' => ['required_with:items', 'uuid', 'distinct', 'exists:bread_categories,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:0', 'max:10000000'],
        ];
    }
}
