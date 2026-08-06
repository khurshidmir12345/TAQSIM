<?php

namespace App\Enums;

/**
 * Bildirishnoma turlari. Foydalanuvchi profilda har birini alohida
 * o'chirib qo'yishi mumkin (`users.notification_prefs`).
 */
enum NotificationCategory: string
{
    /** Har kuni ertalabki tilak. */
    case DailyGreeting = 'daily_greeting';

    /** "Ertaga zakaz bor" / "Bugun zakaz bor" eslatmalari. */
    case OrderReminder = 'order_reminder';

    /** Biznesga yangi xodim qo'shildi. */
    case EmployeeAdded = 'employee_added';

    /** Sinov muddati va boshqa tizim xabarlari. */
    case System = 'system';

    /** Admin paneldan qo'lda yuborilgan xabar. */
    case Admin = 'admin';

    /**
     * Foydalanuvchi o'chira oladigan turlar.
     *
     * `Admin` ro'yxatda yo'q — admin qo'lda yuborgan xabar doim yetkaziladi.
     */
    public function isMutable(): bool
    {
        return $this !== self::Admin;
    }

    /** Sozlamalar ekranida ko'rsatiladigan tartib. */
    public static function preferenceKeys(): array
    {
        return array_map(
            static fn (self $c): string => $c->value,
            array_filter(self::cases(), static fn (self $c): bool => $c->isMutable()),
        );
    }
}
