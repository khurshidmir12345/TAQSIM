<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Do'konning o'z partiya birligi ("Laganda", "Tandir", ...).
 *
 * `icon` — foydalanuvchi tanlagan stiker (emoji); ustun 10 belgigacha.
 */
class StoreCustomBatchUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:30'],
            'icon' => ['required', 'string', 'min:1', 'max:10'],
        ];
    }
}
