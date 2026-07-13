<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shop = $this->route('shop');

        return [
            'delivery_date' => ['sometimes', 'required', 'date'],
            'delivery_time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.bread_category_id' => [
                'required_with:items',
                'uuid',
                Rule::exists('bread_categories', 'id')
                    ->where('shop_id', $shop->id)
                    ->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
