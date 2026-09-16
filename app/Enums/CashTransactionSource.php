<?php

namespace App\Enums;

/**
 * Kassa yozuvi qayerdan paydo bo'lgani.
 *
 * `Manual` — foydalanuvchi kassada o'zi yaratgan.
 * Qolganlari — asosiy sahifadagi amaldan avtomatik ko'chirilgan, do'kon
 * sozlamasi yoqiq bo'lsa. Ular qo'lda tahrirlanmaydi: manbasi o'zgarsa
 * observer o'zi yangilaydi.
 */
enum CashTransactionSource: string
{
    case Manual = 'manual';

    case Production = 'production';

    case BreadReturn = 'return';

    /** Do'kon to'lovi (do'konlar bo'limi) — kirim. */
    case OutletPayment = 'outlet';

    /** Do'konga nasiya berilgan mahsulot puli — chiqim. */
    case OutletCredit = 'outlet_credit';

    /** Do'kondan mahsulot qaytdi — nasiya shunchaga kamaydi (kirim). */
    case OutletReturn = 'outlet_return';

    /** Foydalanuvchi tahrirlashi/o'chirishi mumkinmi. */
    public function isEditable(): bool
    {
        return $this === self::Manual;
    }
}
