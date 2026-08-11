<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Profildan telefon raqamni almashtirish — birinchi qadam (kod so'rash).
 *
 * Raqam bandligi SMS yuborilishidan OLDIN tekshiriladi: foydalanuvchi
 * kodni kutib o'tirib, keyin "bu raqam band" degan xabarni ko'rmasin.
 */
class SendPhoneChangeCodeRequest extends FormRequest
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
                // O'chirilgan (soft-deleted) hisoblar ham hisobga olinadi —
                // aks holda raqam qaytarilganda unique indeks buziladi.
                Rule::unique('users', 'phone')->ignore($this->user()->id),
            ],
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
