<?php

namespace App\Services;

use App\Enums\ShopUserType;
use App\Models\Shop;
use App\Models\User;

/**
 * Qaysi bo'limlar ochiq — shu yerda hal qilinadi.
 *
 * Qoida bitta: hisob **egaga** bog'lanadi. Xodim (seller) egasining muddati
 * ichida ishlaydi, o'zining alohida muddati hisobga olinmaydi
 * (`Shop::owner()` izohiga qarang).
 *
 * Ro'yxatda yo'q hamma narsa — bosh sahifa, mahsulot/xomashyo/retsept,
 * ishlab chiqarish, qaytarish, kassa — doim bepul va bu yerdan o'tmaydi.
 */
class AccessService
{
    /**
     * Do'kondagi ochiq bo'limlar ro'yxati.
     *
     * @return list<string>
     */
    public function featuresFor(Shop $shop): array
    {
        return $this->featuresForUser($this->ownerOf($shop));
    }

    /**
     * Foydalanuvchi (egasi) uchun ochiq bo'limlar.
     *
     * Egasi topilmasa — bo'sh ro'yxat: egasiz do'kon odatda yarim o'chirilgan
     * holat, unga pullik bo'limlarni ochib berish noto'g'ri bo'lardi.
     *
     * @return list<string>
     */
    public function featuresForUser(?User $owner): array
    {
        if ($owner === null) {
            return [];
        }

        return $owner->hasFullAccess() ? self::paidFeatures() : [];
    }

    public function shopHasFeature(Shop $shop, string $feature): bool
    {
        // Ro'yxatda yo'q bo'lim — bepul, tekshirilmaydi.
        if (! in_array($feature, self::paidFeatures(), true)) {
            return true;
        }

        return in_array($feature, $this->featuresFor($shop), true);
    }

    public function userHasFeature(?User $owner, string $feature): bool
    {
        if (! in_array($feature, self::paidFeatures(), true)) {
            return true;
        }

        return in_array($feature, $this->featuresForUser($owner), true);
    }

    /**
     * Do'kon egasi.
     *
     * Ataylab keshlanmaydi: kesh so'rovlar orasida eskirib qolsa, muddati
     * tugagan egaga ochiq javob qaytishi mumkin edi. Buning o'rniga ikkita
     * arzon yo'l bor va ular deyarli har doim ishlaydi:
     *
     *  1. So'rayotgan odamning o'zi egasi bo'lsa — qo'shimcha so'rov yo'q.
     *  2. `userShops.user` eager-load qilingan bo'lsa — do'kon soni qancha
     *     bo'lishidan qat'i nazar qo'shimcha so'rov yo'q
     *     (`ShopController@index` shuni qiladi).
     */
    public function ownerOf(Shop $shop): ?User
    {
        // 1. So'rayotgan odam shu do'konning egasi.
        $current = auth()->user();
        if ($current !== null && self::isOwnerPivot($shop->pivot ?? null)) {
            return $current;
        }

        // 2. Pivot bilan birga egasi ham yuklangan.
        if ($shop->relationLoaded('userShops')) {
            $owner = $shop->userShops
                ->first(fn ($pivot) => self::isOwnerPivot($pivot))
                ?->user;

            if ($owner !== null) {
                return $owner;
            }
        }

        return $shop->owner();
    }

    private static function isOwnerPivot(mixed $pivot): bool
    {
        if ($pivot === null) {
            return false;
        }

        return $pivot->user_type === ShopUserType::Owner
            || $pivot->user_type === ShopUserType::Owner->value;
    }

    /** @return list<string> */
    public static function paidFeatures(): array
    {
        /** @var list<string> $features */
        $features = config('access.paid_features', []);

        return $features;
    }
}
