<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CashTransactionType;
use App\Http\Requests\StoreExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

/**
 * Kassa kategoriyalari — xarajat va kirim uchun.
 *
 * Tizim turlari `config/expense_categories.php` da, foydalanuvchi qo'shganlari
 * `expense_categories` jadvalida. Tizim turlarini tahrirlab yoki o'chirib
 * bo'lmaydi: ular kalit sifatida yozuvlarda saqlanadi va tarjimasi bor.
 */
class ExpenseCategoryController extends BaseShopController
{
    private const LOCALES = ['uz', 'ru', 'kk', 'ky', 'tr', 'uz_CYRL', 'en'];

    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $locale = $this->locale($request);
        $type = $this->type($request);
        $query = mb_strtolower(trim((string) $request->query('search', '')));

        $builtIn = collect(array_keys(config($this->configKey($type), [])))
            ->map(fn (string $key): array => [
                'id' => $key,
                'name' => $this->builtInName($type, $key, $locale),
                'is_system' => true,
                'icon' => config("{$this->configKey($type)}.{$key}.icon", 'category'),
            ]);

        if ($query !== '') {
            $builtIn = $builtIn->filter(
                fn (array $row): bool => str_contains(mb_strtolower($row['name']), $query)
                    || str_contains((string) $row['id'], $query)
            );
        }

        $customQuery = ExpenseCategory::query()
            ->where('shop_id', $shop->id)
            ->where('user_id', $request->user()->id)
            ->where('type', $type)
            ->orderBy('name');

        if ($query !== '') {
            $customQuery->whereRaw('LOWER(name) LIKE ?', ['%'.$query.'%']);
        }

        $custom = $customQuery->get()->map(fn (ExpenseCategory $c): array => [
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

        $type = $this->type($request);
        $name = trim($request->validated()['name']);

        if ($this->nameTaken($shop, $request->user()->id, $type, $name)) {
            return $this->error(__('api.errors.expense_category_duplicate'), 422);
        }

        $row = $shop->expenseCategories()->create([
            'user_id' => $request->user()->id,
            'type' => $type,
            'name' => $name,
        ]);

        return $this->created(['category' => $this->present($row)]);
    }

    /** PUT /v1/shops/{shop}/expense-categories/{category} */
    public function update(Request $request, Shop $shop, string $category): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $row = $this->findOwn($request, $shop, $category);

        if ($row === null) {
            return $this->error(__('api.errors.not_found'), 404);
        }

        $name = trim($request->validate([
            'name' => ['required', 'string', 'max:64'],
        ])['name']);

        if ($this->nameTaken($shop, $request->user()->id, $row->type, $name, $row->id)) {
            return $this->error(__('api.errors.expense_category_duplicate'), 422);
        }

        $row->update(['name' => $name]);

        return $this->success(['category' => $this->present($row->refresh())]);
    }

    /**
     * DELETE /v1/shops/{shop}/expense-categories/{category}
     *
     * Kategoriya ishlatilgan bo'lsa o'chirilmaydi — aks holda eski yozuvlar
     * nomsiz qolib ketardi.
     */
    public function destroy(Request $request, Shop $shop, string $category): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $row = $this->findOwn($request, $shop, $category);

        if ($row === null) {
            return $this->error(__('api.errors.not_found'), 404);
        }

        $inUse = $shop->expenses()->where('category', $row->id)->exists()
            || $shop->cashTransactions()->where('category', $row->id)->exists();

        if ($inUse) {
            return $this->error(__('api.errors.expense_category_in_use'), 422);
        }

        $row->delete();

        return $this->deleted();
    }

    /** Faqat foydalanuvchining o'z kategoriyasi (tizim turi topilmaydi). */
    private function findOwn(Request $request, Shop $shop, string $id): ?ExpenseCategory
    {
        return ExpenseCategory::query()
            ->where('shop_id', $shop->id)
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->first();
    }

    private function nameTaken(
        Shop $shop,
        string $userId,
        string $type,
        string $name,
        ?string $exceptId = null,
    ): bool {
        $query = ExpenseCategory::query()
            ->where('shop_id', $shop->id)
            ->where('user_id', $userId)
            ->where('type', $type)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        return $query->exists();
    }

    /** @return array<string,mixed> */
    private function present(ExpenseCategory $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'is_system' => false,
            'icon' => 'tune',
        ];
    }

    private function type(Request $request): string
    {
        return $request->query('type') === CashTransactionType::Income->value
            ? CashTransactionType::Income->value
            : CashTransactionType::Expense->value;
    }

    private function configKey(string $type): string
    {
        return $type === CashTransactionType::Income->value
            ? 'expense_categories.built_in_income'
            : 'expense_categories.built_in';
    }

    private function builtInName(string $type, string $key, string $locale): string
    {
        return $type === CashTransactionType::Income->value
            ? Lang::get("cash.income_categories.{$key}", [], $locale)
            : Lang::get("expense.categories.{$key}", [], $locale);
    }

    private function locale(Request $request): string
    {
        $locale = (string) $request->query('locale', 'uz');

        if (! in_array($locale, self::LOCALES, true)) {
            $locale = 'uz';
        }

        app()->setLocale($locale);

        return $locale;
    }
}
