<?php

namespace App\Services;

use App\Enums\CashTransactionSource;
use App\Enums\CashTransactionType;
use App\Models\BreadReturn;
use App\Models\CashTransaction;
use App\Models\Production;
use App\Models\Shop;

/**
 * Asosiy sahifadagi amallarni kassa daftariga ko'chiradi.
 *
 * Mahsulot chiqimi ikkita yozuv beradi — tushum (kirim) va xom ashyo
 * qiymati (chiqim); vozvrat esa bitta chiqim.
 *
 * Yozuvlar do'kon sozlamasi yoqiq bo'lgandagina yaratiladi. Manba yozuvi
 * tahrirlansa summalar yangilanadi, o'chirilsa kassadagi aksi ham ketadi —
 * shuning uchun kassa hech qachon "osilib qolgan" yozuv bilan qolmaydi.
 */
class CashMirrorService
{
    /** Avtomatik yozuvlarning kategoriya kaliti (ilova tarjima qiladi). */
    public const CATEGORY_PRODUCTION_INCOME = 'production_income';

    public const CATEGORY_PRODUCTION_COST = 'production_cost';

    public const CATEGORY_RETURN = 'return';

    public function syncProduction(Production $production): void
    {
        $shop = $production->shop;

        if (! $shop instanceof Shop || ! $shop->cash_track_production) {
            $this->forget(CashTransactionSource::Production, $production->id);

            return;
        }

        $production->loadMissing('breadCategory');

        $income = (float) $production->bread_produced
            * (float) ($production->breadCategory?->selling_price ?? 0);
        $cost = (float) $production->ingredient_cost;

        $this->put(
            $shop,
            CashTransactionType::Income,
            CashTransactionSource::Production,
            $production->id,
            self::CATEGORY_PRODUCTION_INCOME,
            $income,
            $production->date,
            $production->created_by,
        );

        $this->put(
            $shop,
            CashTransactionType::Expense,
            CashTransactionSource::Production,
            $production->id,
            self::CATEGORY_PRODUCTION_COST,
            $cost,
            $production->date,
            $production->created_by,
        );
    }

    public function syncReturn(BreadReturn $return): void
    {
        $shop = $return->shop;

        if (! $shop instanceof Shop || ! $shop->cash_track_returns) {
            $this->forget(CashTransactionSource::BreadReturn, $return->id);

            return;
        }

        $this->put(
            $shop,
            CashTransactionType::Expense,
            CashTransactionSource::BreadReturn,
            $return->id,
            self::CATEGORY_RETURN,
            (float) $return->total_amount,
            $return->date,
            $return->created_by,
        );
    }

    public function forgetProduction(Production $production): void
    {
        $this->forget(CashTransactionSource::Production, $production->id);
    }

    public function forgetReturn(BreadReturn $return): void
    {
        $this->forget(CashTransactionSource::BreadReturn, $return->id);
    }

    /**
     * Sozlama o'zgarganda butun do'kon bo'yicha qayta qurish.
     *
     * Yoqilganda mavjud yozuvlar ham kassaga tushishi kerak — aks holda
     * foydalanuvchi tugmani bosib, hech narsa o'zgarmaganini ko'rardi.
     */
    public function resyncShop(Shop $shop): void
    {
        if ($shop->cash_track_production) {
            $shop->productions()->with('breadCategory')->chunkById(200, function ($productions): void {
                foreach ($productions as $production) {
                    $this->syncProduction($production);
                }
            });
        } else {
            $this->forgetAll($shop, CashTransactionSource::Production);
        }

        if ($shop->cash_track_returns) {
            $shop->breadReturns()->chunkById(200, function ($returns): void {
                foreach ($returns as $return) {
                    $this->syncReturn($return);
                }
            });
        } else {
            $this->forgetAll($shop, CashTransactionSource::BreadReturn);
        }
    }

    /**
     * Bir manbaning bitta yo'nalishdagi yozuvini yaratadi yoki yangilaydi.
     *
     * Summa nolga teng bo'lsa yozuv saqlanmaydi — kassada "0 so'm" qatorlari
     * ro'yxatni behuda to'ldirardi.
     */
    private function put(
        Shop $shop,
        CashTransactionType $type,
        CashTransactionSource $source,
        string $sourceId,
        string $category,
        float $amount,
        mixed $date,
        ?string $createdBy,
    ): void {
        $query = CashTransaction::query()
            ->where('source', $source->value)
            ->where('source_id', $sourceId)
            ->where('type', $type->value);

        if ($amount <= 0) {
            $query->delete();

            return;
        }

        $existing = $query->first();

        $attributes = [
            'shop_id' => $shop->id,
            'type' => $type,
            'source' => $source,
            'source_id' => $sourceId,
            'category' => $category,
            'amount' => round($amount, 2),
            'date' => $date,
            'created_by' => $createdBy,
        ];

        $existing ? $existing->update($attributes) : CashTransaction::create($attributes);
    }

    private function forget(CashTransactionSource $source, string $sourceId): void
    {
        CashTransaction::query()
            ->where('source', $source->value)
            ->where('source_id', $sourceId)
            ->delete();
    }

    private function forgetAll(Shop $shop, CashTransactionSource $source): void
    {
        CashTransaction::query()
            ->where('shop_id', $shop->id)
            ->where('source', $source->value)
            ->delete();
    }
}
