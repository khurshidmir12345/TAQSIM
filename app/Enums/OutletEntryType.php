<?php

namespace App\Enums;

/** Do'kon daftaridagi amal turi. */
enum OutletEntryType: string
{
    /** Mahsulot berildi — do'kon qarzi oshadi. */
    case Delivery = 'delivery';

    /** Sotilmagan mahsulot qaytdi — qarz kamayadi. */
    case Return = 'return';

    /** Do'kon pul to'ladi — qarz kamayadi. */
    case Payment = 'payment';

    public function hasItems(): bool
    {
        return $this !== self::Payment;
    }
}
