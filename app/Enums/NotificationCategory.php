<?php

namespace App\Enums;

/**
 * Bildirishnoma turlari.
 *
 * Foydalanuvchi profilda faqat BITTA umumiy tugmani boshqaradi
 * (`users.notification_prefs['enabled']`). O'chirilganda eslatuvchi
 * turlar (kunlik tilak, zakaz eslatmasi) to'xtaydi; hisobga oid
 * muhim xabarlar esa baribir yetkaziladi.
 */
enum NotificationCategory: string
{
    /** Har kuni ertalabki tilak. */
    case DailyGreeting = 'daily_greeting';

    /** "Bugun zakaz bor" eslatmalari. */
    case OrderReminder = 'order_reminder';

    /** Biznesga yangi xodim qo'shildi. */
    case EmployeeAdded = 'employee_added';

    /** Sinov muddati va boshqa tizim xabarlari. */
    case System = 'system';

    /** Admin paneldan qo'lda yuborilgan xabar. */
    case Admin = 'admin';

    /**
     * Umumiy tugma o'chirilganda to'xtaydigan turlar.
     *
     * Xodim qo'shilishi, tizim xabarlari va admin xabari ro'yxatda yo'q —
     * ular majburiy, sozlamadan qat'i nazar yetkaziladi.
     */
    public function isOptional(): bool
    {
        return match ($this) {
            self::DailyGreeting, self::OrderReminder => true,
            default => false,
        };
    }

    /**
     * Eski mobil versiyalar yuboradigan/kutadigan kalitlar.
     *
     * Yangi ilovada tur bo'yicha alohida tugma yo'q, lekin foydalanuvchilar
     * qo'lidagi eski versiya bu kalitlarni hamon jo'natadi — API ularni
     * rad etmasligi kerak.
     *
     * @return array<int,string>
     */
    public static function legacyPreferenceKeys(): array
    {
        return [
            self::DailyGreeting->value,
            self::OrderReminder->value,
            self::EmployeeAdded->value,
            self::System->value,
        ];
    }
}
