<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCashEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'string', 'max:64'],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:9999999999999'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'date' => ['sometimes', 'date'],
        ];
    }
}
