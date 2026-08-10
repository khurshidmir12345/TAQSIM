<?php

namespace App\Enums;

/** Kassa yozuvining yo'nalishi. */
enum CashTransactionType: string
{
    case Income = 'income';

    case Expense = 'expense';

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
