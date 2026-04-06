<?php

namespace App\Enums;

enum IngredientUnit: string
{
    case Kg = 'kg';
    case Gram = 'gram';
    case Litr = 'litr';
    case Ml = 'ml';
    case Dona = 'dona';
    case Metr = 'metr';

    public function label(): string
    {
        return match ($this) {
            self::Kg => 'Kilogramm',
            self::Gram => 'Gramm',
            self::Litr => 'Litr',
            self::Ml => 'Millilitr',
            self::Dona => 'Dona',
            self::Metr => 'Metr',
        };
    }
}
