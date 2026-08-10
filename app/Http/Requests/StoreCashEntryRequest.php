<?php

namespace App\Http\Requests;

use App\Enums\CashTransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(CashTransactionType::values())],
            'category' => ['nullable', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999'],
            'description' => ['nullable', 'string', 'max:1000'],
            'date' => ['required', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Sana berilmasa bugungi kun — ilova ko'p hollarda joriy kunni yozadi.
        if (! $this->has('date')) {
            $this->merge([
                'date' => now(config('app.business_timezone'))->toDateString(),
            ]);
        }
    }
}
