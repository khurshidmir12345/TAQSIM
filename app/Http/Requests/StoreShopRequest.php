<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_type_id'         => ['required', 'uuid', 'exists:business_types,id'],
            'currency_id'              => ['required', 'uuid', 'exists:currencies,id'],
            'custom_business_type_name'=> ['nullable', 'string', 'max:100'],
            'ingredient_unit_ids'      => ['nullable', 'array'],
            'ingredient_unit_ids.*'    => ['uuid', 'exists:measurement_units,id'],
            'batch_unit_ids'           => ['nullable', 'array'],
            'batch_unit_ids.*'         => ['uuid', 'exists:measurement_units,id'],
            'name'                     => ['required', 'string', 'max:255'],
            'description'              => ['nullable', 'string', 'max:1000'],
            'address'                  => ['nullable', 'string', 'max:500'],
            'phone'                    => ['nullable', 'string', 'max:32'],
            'latitude'                 => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'                => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
