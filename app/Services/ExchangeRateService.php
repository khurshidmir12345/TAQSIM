<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    /** Joriy USD→UZS kursi (faol yozuv yoki zaxira config). */
    public function usdToUzs(): float
    {
        $rate = ExchangeRate::query()
            ->where('base_code', 'USD')
            ->where('quote_code', 'UZS')
            ->where('is_active', true)
            ->value('rate');

        return $rate !== null
            ? (float) $rate
            : (float) config('billing.default_usd_uzs');
    }

    /** USD narxni mahalliy (UZS) summaga aylantiradi va yaxlitlaydi. */
    public function convertUsdToUzs(float $usd): float
    {
        $local = $usd * $this->usdToUzs();
        $step = (int) config('billing.local_rounding', 0);

        if ($step > 0) {
            $local = round($local / $step) * $step;
        }

        return round($local, 2);
    }

    /**
     * CBU (Markaziy bank) API'dan USD kursini olib, jadvalni yangilaydi.
     * Muvaffaqiyatsiz bo'lsa mavjud/zaxira kurs saqlanadi.
     */
    public function syncFromCbu(): ?float
    {
        try {
            $response = Http::timeout(15)->get((string) config('billing.cbu_url'));

            if (! $response->successful()) {
                Log::warning('CBU exchange sync failed', ['status' => $response->status()]);

                return null;
            }

            $data = $response->json();
            $usd = is_array($data) ? ($data[0] ?? null) : null;
            $rate = $usd['Rate'] ?? null;

            if ($rate === null) {
                return null;
            }

            $rate = (float) $rate;

            ExchangeRate::query()->updateOrCreate(
                ['base_code' => 'USD', 'quote_code' => 'UZS'],
                [
                    'rate' => $rate,
                    'source' => 'cbu',
                    'is_active' => true,
                    'fetched_at' => now(),
                ],
            );

            return $rate;
        } catch (\Throwable $e) {
            Log::error('CBU exchange sync error', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
