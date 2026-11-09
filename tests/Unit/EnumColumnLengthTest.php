<?php

namespace Tests\Unit;

use App\Enums\CashTransactionSource;
use App\Enums\IngredientMovementType;
use PHPUnit\Framework\TestCase;

/**
 * Enum qiymatlari MySQL ustuniga sig'ishi kerak — testlar sqlite'da ishlaydi
 * va u uzunlikni tekshirmaydi, xato faqat serverda chiqardi.
 */
class EnumColumnLengthTest extends TestCase
{
    public function test_cash_sources_fit_varchar_16(): void
    {
        foreach (CashTransactionSource::cases() as $case) {
            $this->assertLessThanOrEqual(16, strlen($case->value), $case->value);
        }
    }

    public function test_ingredient_movement_types_fit_varchar_16(): void
    {
        foreach (IngredientMovementType::cases() as $case) {
            $this->assertLessThanOrEqual(16, strlen($case->value), $case->value);
        }
    }
}
