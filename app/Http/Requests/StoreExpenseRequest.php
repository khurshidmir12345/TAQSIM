<?php

namespace App\Http\Requests;

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:64', $this->categoryRule()],
            'description' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'date' => ['required', 'date'],
        ];
    }

    private function categoryRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $shop = $this->route('shop');
            if ($shop === null) {
                $fail(__('api.errors.validation_failed'));

                return;
            }
            $builtIn = array_keys(config('expense_categories.built_in', []));
            if (in_array($value, $builtIn, true)) {
                return;
            }
            $ok = ExpenseCategory::query()
                ->where('shop_id', $shop->id)
                ->where('user_id', $this->user()->id)
                ->where('id', $value)
                ->exists();
            if (! $ok) {
                $fail(__('api.errors.invalid_expense_category'));
            }
        };
    }
}
