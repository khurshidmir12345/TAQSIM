<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32'],
        ];
    }
}
