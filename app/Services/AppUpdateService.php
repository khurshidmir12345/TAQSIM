<?php

namespace App\Services;

use App\Models\SystemLink;

/**
 * Ilova versiyasini `.env` dagi oxirgi versiya bilan solishtiradi.
 *
 * Solishtirish serverda bajariladi — qoida bitta joyda tursin, ilovaning
 * eski nusxalari ham to'g'ri javob olsin.
 */
class AppUpdateService
{
    /** Qo'llab-quvvatlanadigan platformalar. */
    private const PLATFORMS = ['android', 'ios'];

    /**
     * Do'kon havolasi saqlanadigan `system_links.type` qiymatlari.
     *
     * Havola admin paneldan tahrirlanishi kerak — ilova do'konda qayta
     * nomlansa yoki manzil o'zgarsa, deploy kutib o'tirmaslik uchun.
     */
    private const STORE_LINK_TYPE = [
        'ios' => 'app_store',
        'android' => 'play_store',
    ];

    /**
     * @return array{
     *     enabled: bool,
     *     update_available: bool,
     *     platform: string,
     *     current_version: ?string,
     *     latest_version: ?string,
     *     store_url: ?string
     * }
     */
    public function check(?string $platform, ?string $currentVersion): array
    {
        $platform = in_array($platform, self::PLATFORMS, true) ? $platform : 'android';
        $enabled = (bool) config('app_update.enabled');
        $latest = trim((string) config("app_update.{$platform}.version"));
        $storeUrl = $this->storeUrl($platform);

        $updateAvailable = $enabled
            && $latest !== ''
            && $currentVersion !== null
            && $currentVersion !== ''
            && self::compare($currentVersion, $latest) < 0;

        return [
            'enabled' => $enabled,
            'update_available' => $updateAvailable,
            'platform' => $platform,
            'current_version' => $currentVersion ?: null,
            'latest_version' => $latest !== '' ? $latest : null,
            'store_url' => $storeUrl,
        ];
    }

    /**
     * Do'kon havolasi: avval bazadagi faol yozuv, bo'lmasa `.env` dagi qiymat.
     *
     * Baza ustun turadi — havolani admin paneldan o'zgartirish mumkin bo'lsin.
     */
    private function storeUrl(string $platform): ?string
    {
        $fromDb = SystemLink::active()
            ->where('type', self::STORE_LINK_TYPE[$platform])
            ->value('url');

        $url = trim((string) ($fromDb ?? config("app_update.{$platform}.url")));

        return $url !== '' ? $url : null;
    }

    /**
     * Ikki versiyani solishtiradi: `a < b` bo'lsa manfiy, teng bo'lsa 0,
     * `a > b` bo'lsa musbat.
     *
     * `1.2.7+39` ko'rinishi ham tushuniladi — build raqami faqat semantik
     * qismlar teng bo'lganda hal qiluvchi bo'ladi (`1.2.7+40 > 1.2.7+39`).
     * Raqam bo'lmagan qismlar (`1.2.7-beta`) 0 deb olinadi.
     */
    public static function compare(string $a, string $b): int
    {
        [$aParts, $aBuild] = self::split($a);
        [$bParts, $bBuild] = self::split($b);

        $length = max(count($aParts), count($bParts));

        for ($i = 0; $i < $length; $i++) {
            $diff = ($aParts[$i] ?? 0) <=> ($bParts[$i] ?? 0);

            if ($diff !== 0) {
                return $diff;
            }
        }

        return $aBuild <=> $bBuild;
    }

    /**
     * `1.2.7+39` → `[[1, 2, 7], 39]`
     *
     * @return array{0: list<int>, 1: int}
     */
    private static function split(string $version): array
    {
        $version = trim($version);
        $build = 0;

        if (str_contains($version, '+')) {
            [$version, $rawBuild] = explode('+', $version, 2);
            $build = (int) preg_replace('/\D/', '', $rawBuild);
        }

        $parts = array_map(
            static fn (string $part): int => (int) preg_replace('/\D.*$/', '', $part),
            explode('.', $version)
        );

        return [array_values($parts), $build];
    }
}
