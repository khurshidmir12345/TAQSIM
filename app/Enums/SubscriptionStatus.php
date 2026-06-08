<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case Grace = 'grace';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /** Foydalanuvchi to'liq imkoniyatlardan foydalana oladimi (yozish ham). */
    public function hasFullAccess(): bool
    {
        return $this === self::Trialing || $this === self::Active;
    }

    /** Faqat o'qish (grace davri). */
    public function isReadOnly(): bool
    {
        return $this === self::Grace;
    }

    public function isBlocked(): bool
    {
        return $this === self::Expired || $this === self::Cancelled;
    }
}
