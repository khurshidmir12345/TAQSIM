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
            'recipe_id' => ['required', 'uuid', 'exists:recipes,id'],
            'bread_category_id' => ['required', 'uuid', 'exists:bread_categories,id'],
            'date' => ['required', 'date'],
            'batch_count' => ['required', 'numeric', 'min:0.1'],
        ];
    }
}
