<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeliverCustomerOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_amount' => ['nullable', 'numeric', 'gt:0'],
            'payment_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
