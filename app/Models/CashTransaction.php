<?php

namespace App\Models;

use App\Enums\CashTransactionSource;
use App\Enums\CashTransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'shop_id',
        'type',
        'source',
        'source_id',
        'category',
        'amount',
        'description',
        'date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CashTransactionType::class,
            'source' => CashTransactionSource::class,
            'amount' => 'decimal:2',
            'date' => 'date',
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

    /** @param  Builder<self>  $query */
    public function scopeIncome(Builder $query): void
    {
        $query->where('type', CashTransactionType::Income->value);
    }

    /** @param  Builder<self>  $query */
    public function scopeExpense(Builder $query): void
    {
        $query->where('type', CashTransactionType::Expense->value);
    }

    /** Foydalanuvchi o'zi yaratgan yozuvlar — faqat ularni tahrirlash mumkin. */
    public function isEditable(): bool
    {
        return $this->source->isEditable();
    }
}
