<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOutletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phones' => ['nullable', 'array', 'max:10'],
            // Bo'sh qatorlar kontrollerda tozalanadi, 3 tasi qoladi (Outlet::MAX_PHONES).
            'phones.*' => ['nullable', 'string', 'max:32'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
