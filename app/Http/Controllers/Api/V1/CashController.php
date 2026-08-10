<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CashTransactionType;
use App\Http\Requests\StoreCashEntryRequest;
use App\Http\Requests\UpdateCashEntryRequest;
use App\Models\Shop;
use App\Services\CashMirrorService;
use App\Services\CashService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rule;

/**
 * Kassa — barcha kirim/chiqim va davr foydasi.
 */
class CashController extends BaseShopController
{
    public function __construct(
        private readonly CashService $cash,
        private readonly CashMirrorService $mirror,
    ) {}

    /**
     * GET /v1/shops/{shop}/cash
     * Davr xulosasi + birinchi sahifadagi yozuvlar — ekran bitta so'rovda
     * to'liq chiziladi.
     */
    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $validated = $request->validate([
            'period' => ['sometimes', Rule::in([
                CashService::PERIOD_DAY,
                CashService::PERIOD_WEEK,
                CashService::PERIOD_MONTH,
            ])],
            'date' => ['sometimes', 'date'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $period = $validated['period'] ?? CashService::PERIOD_DAY;

        // Ilovadagi davr tanlagichi aniq oraliq beradi (masalan o'tgan hafta) —
        // berilgan bo'lsa u ustun turadi.
        if (isset($validated['from'], $validated['to'])) {
            $from = Carbon::parse($validated['from'])->toDateString();
            $to = Carbon::parse($validated['to'])->toDateString();
        } else {
            ['from' => $from, 'to' => $to] = $this->cash->resolvePeriod($period, $validated['date'] ?? null);
        }

        $entries = $this->cash->entries($shop, $from, $to);

        return $this->success([
            'period' => $period,
            'summary' => $this->cash->summary($shop, $from, $to),
            'settings' => $this->cash->settings($shop),
            'entries' => array_map($this->presentEntry(...), $entries->items()),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    /** POST /v1/shops/{shop}/cash */
    public function store(StoreCashEntryRequest $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validated();
        $type = CashTransactionType::from($data['type']);

        $entry = $this->cash->create($shop, $type, $data, $request->user()->id);

        return $this->created(['entry' => $this->presentEntry($entry)]);
    }

    /** PUT /v1/shops/{shop}/cash/{entry} */
    public function update(UpdateCashEntryRequest $request, Shop $shop, string $entry): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $found = $this->cash->find($shop, $entry);

        if ($found === null) {
            return $this->error(__('api.errors.not_found'), 404);
        }

        [, $model] = $found;

        // Avtomatik yozuv manbadan kelib chiqadi — uni qo'lda o'zgartirish
        // kassani asosiy sahifadan ajratib yuborardi.
        if (method_exists($model, 'isEditable') && ! $model->isEditable()) {
            return $this->error(__('api.cash.auto_entry_readonly'), 422);
        }

        return $this->success([
            'entry' => $this->presentEntry($this->cash->update($model, $request->validated())),
        ]);
    }

    /** DELETE /v1/shops/{shop}/cash/{entry} */
    public function destroy(Request $request, Shop $shop, string $entry): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $found = $this->cash->find($shop, $entry);

        if ($found === null) {
            return $this->error(__('api.errors.not_found'), 404);
        }

        [, $model] = $found;

        if (method_exists($model, 'isEditable') && ! $model->isEditable()) {
            return $this->error(__('api.cash.auto_entry_readonly'), 422);
        }

        $model->delete();

        return $this->deleted();
    }

    /** GET /v1/shops/{shop}/cash/settings */
    public function settings(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        return $this->success(['settings' => $this->cash->settings($shop)]);
    }

    /**
     * PUT /v1/shops/{shop}/cash/settings
     * Sozlama o'zgarishi bilan avtomatik yozuvlar qayta quriladi.
     */
    public function updateSettings(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $validated = $request->validate([
            'track_production' => ['sometimes', 'boolean'],
            'track_returns' => ['sometimes', 'boolean'],
        ]);

        return $this->success([
            'settings' => $this->cash->updateSettings($shop, $validated, $this->mirror),
        ]);
    }

    /**
     * GET /v1/shops/{shop}/cash/income-categories
     * Kirim turlari — foydalanuvchi tilida.
     */
    public function incomeCategories(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $locale = app()->getLocale();
        $keys = array_keys(Lang::get('cash.income_categories', [], 'uz'));

        return $this->success([
            'categories' => array_map(static fn (string $key): array => [
                'key' => $key,
                'name' => Lang::get("cash.income_categories.{$key}", [], $locale),
            ], $keys),
        ]);
    }

    /**
     * Yozuvni ilova kutgan shaklga keltiradi.
     *
     * `is_editable` — avtomatik yozuvda `false`, ilova unga tahrir tugmasini
     * ko'rsatmasligi kerak.
     */
    private function presentEntry(object $entry): array
    {
        $source = (string) $entry->source;
        $category = $entry->category ?: 'boshqa';

        return [
            'id' => (string) $entry->id,
            'type' => (string) $entry->type,
            'source' => $source,
            'category' => $category,
            'category_name' => $this->categoryName($source, $category),
            'amount' => (float) $entry->amount,
            'description' => $entry->description,
            'date' => $entry->date instanceof \DateTimeInterface
                ? $entry->date->format('Y-m-d')
                : (string) $entry->date,
            'created_at' => $entry->created_at instanceof \DateTimeInterface
                ? $entry->created_at->format(\DateTimeInterface::ATOM)
                : (string) $entry->created_at,
            'is_editable' => $source === 'manual',
        ];
    }

    /**
     * Kategoriya nomi. Avtomatik yozuvlar va kirim turlari `cash.php` dan,
     * xarajat turlari mavjud `expense.php` dan olinadi; topilmasa — kalitning
     * o'zi (foydalanuvchi qo'shgan kategoriya nomi).
     */
    private function categoryName(string $source, string $category): string
    {
        $locale = app()->getLocale();

        if ($source !== 'manual') {
            return Lang::get("cash.auto_categories.{$category}", [], $locale);
        }

        foreach (["cash.income_categories.{$category}", "expense.categories.{$category}"] as $key) {
            $translated = Lang::get($key, [], $locale);

            if ($translated !== $key) {
                return $translated;
            }
        }

        return $category;
    }
}
