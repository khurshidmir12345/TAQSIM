<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Profildan telefon raqamni almashtirish — ikkinchi qadam (kodni tasdiqlash).
 *
 * Kod tasdiqlanmaguncha raqam o'zgarmaydi — eski raqam tasdiqlangan holida
 * qolaveradi.
 */
class ChangePhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                'max:32',
                Rule::unique('users', 'phone')->ignore($this->user()->id),
            ],
            'code' => ['required', 'string', 'size:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => __('api.auth.phone_change_taken'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->phone)) {
            $this->merge(['phone' => preg_replace('/\s+/', '', $this->phone)]);
        }
    }
}
