<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', 'unique:users,email,' . $this->user()->id],
            'locale' => ['sometimes', 'string', 'in:uz,uz_CYRL,ru,kk,ky,tr,tg,en'],
        ];
    }
}
