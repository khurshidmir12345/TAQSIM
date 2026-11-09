<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Eski ilova partiyadagi bitta qator ulushini yuborishi mumkin
            // (masalan 0.05) — shuning uchun pastki chegara kichik.
            // Retseptsiz chiqimda partiya yo'q — faqat `items`.
            'batch_count' => ['nullable', 'numeric', 'min:0.0001'],
            'items' => ['sometimes', 'array', 'min:1', 'max:30'],
            'items.*.bread_category_id' => ['required_with:items', 'uuid', 'distinct', 'exists:bread_categories,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:0', 'max:10000000'],
        ];
    }
}
