<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'date' => ['sometimes', 'date'],
        ];
    }
}
