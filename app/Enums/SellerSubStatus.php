<?php

namespace App\Enums;

/**
 * Xodim (seller) obunasi holati.
 * Trial yo'q — seller faqat `active` bo'lsa ishlay oladi.
 */
enum SellerSubStatus: string
{
    case Active = 'active';
    case PastDue = 'past_due';   // pulli o'rin to'lovi qaytdi
    case Expired = 'expired';    // bekor qilingan / muddati tugagan

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
