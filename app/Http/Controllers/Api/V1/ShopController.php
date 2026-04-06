<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ShopUserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShopRequest;
use App\Http\Requests\UpdateShopRequest;
use App\Http\Resources\ShopResource;
use App\Models\CustomBusinessType;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shops = $request->user()
            ->shops()
            ->with(['businessType', 'currency'])
            ->orderBy('name')
            ->get();

        return $this->success([
            'shops' => ShopResource::collection($shops),
        ]);
    }

    public function store(StoreShopRequest $request): JsonResponse
    {
        $shop = Shop::create([
            'business_type_id' => $request->business_type_id,
            'currency_id'      => $request->currency_id,
            'name'             => $request->name,
            'slug'             => Str::slug($request->name) . '-' . Str::random(5),
            'description'      => $request->description,
            'address'          => $request->address,
            'phone'            => $request->phone,
            'latitude'         => $request->latitude,
            'longitude'        => $request->longitude,
        ]);

        $request->user()->shops()->attach($shop->id, [
            'user_type' => ShopUserType::Owner,
        ]);

        // "Boshqa" kategoriya uchun custom nomi saqlash
        if ($request->filled('custom_business_type_name')) {
            CustomBusinessType::create([
                'shop_id' => $shop->id,
                'name'    => $request->custom_business_type_name,
            ]);
        }

        // O'lchov birliklarini biriktirish
        $unitIds = array_merge(
            $request->input('ingredient_unit_ids', []),
            $request->input('batch_unit_ids', []),
        );
        if (! empty($unitIds)) {
            $shop->measurementUnits()->sync($unitIds);
        }

        $shop->load(['businessType', 'currency', 'measurementUnits', 'customBusinessType']);

        return $this->created([
            'shop' => new ShopResource($shop),
        ], __('api.shop.created'));
    }

    public function show(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $shop->load(['businessType', 'currency']);

        return $this->success([
            'shop' => new ShopResource($shop),
        ]);
    }

    public function update(UpdateShopRequest $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $shop->update($request->validated());
        $shop->load('businessType');

        return $this->success([
            'shop' => new ShopResource($shop->fresh()),
        ], __('api.updated'));
    }

    public function destroy(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop, ownerOnly: true);

        $shop->delete();

        return $this->deleted(__('api.shop.deleted'));
    }

    private function authorizeShop(Request $request, Shop $shop, bool $ownerOnly = false): void
    {
        $pivot = $request->user()->userShops()->where('shop_id', $shop->id)->first();

        if (! $pivot) {
            abort(403, __('api.errors.forbidden_shop'));
        }

        if ($ownerOnly && $pivot->user_type !== ShopUserType::Owner) {
            abort(403, __('api.errors.forbidden_owner_only'));
        }
    }
}
