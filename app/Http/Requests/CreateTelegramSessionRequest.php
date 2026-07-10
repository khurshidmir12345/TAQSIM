<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTelegramSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_platform' => ['sometimes', 'string', 'in:mobile,web'],
        ];
    }

    /** Yuborilmagan bo'lsa eski mobile klientlar bilan mos kelishi uchun standart qiymat. */
    public function clientPlatform(): string
    {
        return $this->validated('client_platform') ?? 'mobile';
    }
}
