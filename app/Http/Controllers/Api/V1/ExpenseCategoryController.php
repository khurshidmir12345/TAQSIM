<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

class ExpenseCategoryController extends BaseShopController
{
    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $locale = $request->query('locale', 'uz');
        $allowed = ['uz', 'ru', 'kk', 'ky', 'tr', 'tg', 'uz_CYRL'];
        if (! in_array($locale, $allowed, true)) {
            $locale = 'uz';
        }
        app()->setLocale($locale);

        $q = mb_strtolower(trim((string) $request->query('search', '')));

        $builtIn = collect(array_keys(config('expense_categories.built_in', [])))
            ->map(function (string $key) use ($locale) {
                $icon = config("expense_categories.built_in.$key.icon", 'category');

                return [
                    'id' => $key,
                    'name' => Lang::get('expense.categories.'.$key, [], $locale),
                    'is_system' => true,
                    'icon' => $icon,
                ];
            });

        if ($q !== '') {
            $builtIn = $builtIn->filter(function (array $row) use ($q): bool {
                $name = mb_strtolower($row['name']);

                return str_contains($name, $q) || str_contains((string) $row['id'], $q);
            });
        }

        $customQuery = ExpenseCategory::query()
            ->where('shop_id', $shop->id)
            ->where('user_id', $request->user()->id)
            ->orderBy('name');

        if ($q !== '') {
            $customQuery->whereRaw('LOWER(name) LIKE ?', ['%'.$q.'%']);
        }

        $custom = $customQuery->get()->map(fn (ExpenseCategory $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'is_system' => false,
            'icon' => 'tune',
        ]);

        return $this->success([
            'categories' => $builtIn->values()->concat($custom)->values(),
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $name = trim($request->validated()['name']);

        $exists = ExpenseCategory::query()
            ->where('shop_id', $shop->id)
            ->where('user_id', $request->user()->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($exists) {
            return $this->error(__('api.errors.expense_category_duplicate'), 422);
        }

        $row = $shop->expenseCategories()->create([
            'user_id' => $request->user()->id,
            'name' => $name,
        ]);

        return $this->created([
            'category' => [
                'id' => $row->id,
                'name' => $row->name,
                'is_system' => false,
                'icon' => 'tune',
            ],
        ]);
    }
}
