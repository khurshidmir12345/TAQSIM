<?php

namespace App\Http\Requests;

use App\Enums\OutletEntryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Daftar qatori.
 *
 * delivery/return — `items` shart (mahsulot + miqdor, narx ixtiyoriy: berilmasa
 * mahsulotning sotuv narxi). delivery da `paid_amount` — darhol berilgan naqd.
 * payment — faqat `amount`.
 */
class StoreOutletEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shop = $this->route('shop');
        $type = $this->input('type');
        $withItems = in_array($type, [OutletEntryType::Delivery->value, OutletEntryType::Return->value], true);

        return [
            'type' => ['required', Rule::enum(OutletEntryType::class)],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'amount' => [Rule::requiredIf($type === OutletEntryType::Payment->value), 'numeric', 'min:0.01'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => [Rule::requiredIf($withItems), 'array', 'min:1'],
            'items.*.bread_category_id' => [
                'required', 'uuid',
                Rule::exists('bread_categories', 'id')->where('shop_id', $shop->id),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
