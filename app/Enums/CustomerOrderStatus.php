<?php

namespace App\Enums;

enum CustomerOrderStatus: string
{
    case Active = 'active';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
