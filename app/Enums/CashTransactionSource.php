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

    /** Foydalanuvchi tahrirlashi/o'chirishi mumkinmi. */
    public function isEditable(): bool
    {
        return $this === self::Manual;
    }
}
