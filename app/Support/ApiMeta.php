<?php

namespace App\Support;

/**
 * API javoblari uchun umumiy meta yordamchisi.
 *
 * Bir joyda joriy foydalanuvchining rolini (user_type) aniqlaydi, shunda
 * ApiResponse trait ham, middlewarelar ham, raw response()->json() ham bir
 * xil formatda meta.user_type qaytaradi (3-bo'lim talabi).
 */
final class ApiMeta
{
    /**
     * Joriy autentifikatsiyalangan foydalanuvchining global roli ('owner'|'seller'),
     * yoki auth bo'lmasa null.
     */
    public static function userType(): ?string
    {
        $user = auth()->user();

        return $user?->globalUserType();
    }

    /**
     * Berilgan meta massiviga user_type ni qo'shadi (auth bo'lganda).
     * Mavjud user_type qiymati ustidan yozilmaydi.
     */
    public static function withUserType(array $meta = []): array
    {
        if (! array_key_exists('user_type', $meta)) {
            $type = self::userType();

            if ($type !== null) {
                $meta['user_type'] = $type;
            }
        }

        return $meta;
    }

    /**
     * Tayyor javob massiviga meta.user_type ni qo'shadi (auth bo'lganda).
     * Avval mavjud meta bilan birlashtiradi; bo'sh bo'lsa meta qo'shilmaydi.
     */
    public static function decorate(array $payload): array
    {
        $meta = self::withUserType($payload['meta'] ?? []);

        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return $payload;
    }
}
