<?php

namespace App\Enums;

enum ShopUserType: string
{
    case Owner = 'owner';
    case Seller = 'seller';

    /** Admin panelda ko'rsatiladigan nom. */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Egasi',
            self::Seller => 'Sotuvchi',
        };
    }

    /** Admin paneldagi nishon rangi. */
    public function badgeColor(): string
    {
        return match ($this) {
            self::Owner => 'success',
            self::Seller => 'info',
        };
    }

    /**
     * Qiymatni enum'ga keltiradi.
     *
     * Pivot ustuni ba'zi joyda enum, ba'zi joyda xom satr bo'lib keladi
     * (`whenPivotLoaded`, Filament ustunlari) — shuning uchun ikkalasi ham
     * qabul qilinadi. Tanimasa `null`.
     */
    public static function resolve(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        return is_string($value) ? self::tryFrom($value) : null;
    }
}
