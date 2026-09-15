<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreCustomBatchUnitRequest;
use App\Http\Resources\MeasurementUnitResource;
use App\Models\MeasurementUnit;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Retsept partiya birliklari — tizimdagilar + do'konning o'zi qo'shganlari.
 *
 * Maxsus birlik faqat shu do'konga ko'rinadi. Nomi barcha tillar uchun bir
 * xil saqlanadi (foydalanuvchi o'z tilida yozadi), stikerini o'zi tanlaydi.
 */
class ShopBatchUnitController extends BaseShopController
{
    /** GET /v1/shops/{shop}/measurement-units/batch */
    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $units = MeasurementUnit::active()
            ->batchAvailableFor($shop->id)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        return $this->success(MeasurementUnitResource::collection($units));
    }

    /** POST /v1/shops/{shop}/measurement-units */
    public function store(StoreCustomBatchUnitRequest $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validated();
        $name = trim($data['name']);
        $icon = trim($data['icon']);

        $own = MeasurementUnit::query()->where('shop_id', $shop->id)->batch();

        if ($own->count() >= MeasurementUnit::MAX_CUSTOM_PER_SHOP) {
            return $this->error(__('api.errors.batch_unit_limit'), 422);
        }

        $duplicate = MeasurementUnit::active()
            ->batchAvailableFor($shop->id)
            ->whereRaw('LOWER(name_uz) = ?', [mb_strtolower($name)])
            ->exists();

        if ($duplicate) {
            return $this->error(__('api.errors.batch_unit_duplicate'), 422);
        }

        $maxSort = (int) MeasurementUnit::query()->batch()->max('sort_order');

        $unit = MeasurementUnit::create([
            'shop_id'      => $shop->id,
            'type'         => 'batch',
            'code'         => MeasurementUnit::CUSTOM_CODE_PREFIX . Str::lower(Str::random(12)),
            'name_uz'      => $name,
            'name_uz_cyrl' => $name,
            'name_ru'      => $name,
            'name_kk'      => $name,
            'name_ky'      => $name,
            'name_tr'      => $name,
            'icon'         => $icon,
            'sort_order'   => min($maxSort + 1, 255),
            'is_active'    => true,
        ]);

        return $this->created(new MeasurementUnitResource($unit));
    }

    /**
     * DELETE /v1/shops/{shop}/measurement-units/{unit}
     *
     * Faqat do'konning o'z birligi; retseptda ishlatilgan bo'lsa o'chirilmaydi.
     */
    public function destroy(Request $request, Shop $shop, string $unit): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $row = MeasurementUnit::query()
            ->where('shop_id', $shop->id)
            ->whereKey($unit)
            ->first();

        if ($row === null) {
            return $this->error(__('api.errors.not_found'), 404);
        }

        $inUse = $shop->recipes()->withTrashed()->where('measurement_unit_id', $row->id)->exists();

        if ($inUse) {
            return $this->error(__('api.errors.batch_unit_in_use'), 422);
        }

        $row->delete();

        return $this->deleted();
    }
}
