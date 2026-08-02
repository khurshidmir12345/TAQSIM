<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shop = $this->route('shop');

        return [
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
            'customer' => ['nullable', 'array'],
            'customer.name' => ['required_with:customer', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:32'],
            'customer.note' => ['nullable', 'string', 'max:2000'],
            'delivery_date' => ['required', 'date'],
            'delivery_time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.bread_category_id' => [
                'required',
                'uuid',
                Rule::exists('bread_categories', 'id')
                    ->where('shop_id', $shop->id)
                    ->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'advance_amount' => ['nullable', 'numeric', 'gt:0'],
            'advance_paid_at' => ['nullable', 'date'],
            'advance_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasCustomerId = filled($this->input('customer_id'));
            $hasInlineCustomer = is_array($this->input('customer'));

            if ($hasCustomerId === $hasInlineCustomer) {
                $validator->errors()->add(
                    'customer_id',
                    __('api.errors.customer_order_customer_required')
                );
            }

            if ($hasCustomerId) {
                $shop = $this->route('shop');
                $customer = Customer::query()->find($this->input('customer_id'));

                if ($customer && $customer->shop_id !== $shop->id) {
                    $validator->errors()->add('customer_id', __('api.errors.not_found'));
                }
            }
        });
    }
}
