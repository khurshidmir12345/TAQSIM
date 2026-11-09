<?php

namespace App\Enums;

/** Ombordagi xom ashyo harakati turi. */
enum IngredientMovementType: string
{
    /** Xarid — ombor +, kassadan chiqim. */
    case Purchase = 'purchase';

    /** Mahsulot chiqimida sarflandi — ombor −. */
    case Consumption = 'consumption';

    /** Sanash: haqiqiy qoldiq bilan farq (+ yoki −). */
    case Adjustment = 'adjustment';
}
