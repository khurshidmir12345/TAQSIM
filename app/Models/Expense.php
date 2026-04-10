<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

class Expense extends Model
{
    use HasUuids;

    protected $fillable = [
        'shop_id',
        'category',
        'description',
        'amount',
        'date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categoryLabel(Request $request): string
    {
        $locale = $request->query('locale', 'uz');
        $allowed = ['uz', 'ru', 'kk', 'ky', 'tr', 'uz_CYRL'];
        if (! in_array($locale, $allowed, true)) {
            $locale = 'uz';
        }
        app()->setLocale($locale);

        $builtIn = array_keys(config('expense_categories.built_in', []));
        if (in_array($this->category, $builtIn, true)) {
            return Lang::get('expense.categories.'.$this->category);
        }

        if ($this->categoryLooksLikeUuid($this->category)) {
            return ExpenseCategory::query()->find($this->category)?->name ?? $this->category;
        }

        return $this->category;
    }

    private function categoryLooksLikeUuid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value
        );
    }
}
