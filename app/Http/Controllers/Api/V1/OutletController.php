<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreOutletRequest;
use App\Http\Requests\UpdateOutletRequest;
use App\Http\Resources\OutletResource;
use App\Models\Outlet;
use App\Models\Shop;
use App\Services\OutletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Do'konlar — kartochka va jami hisob. */
class OutletController extends BaseShopController
{
    public function __construct(private readonly OutletService $outlets) {}

    /** GET /v1/shops/{shop}/outlets */
    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $rows = $shop->outlets()->orderBy('name')->get();
        $totals = $this->outlets->totalsByOutlet($shop);

        return $this->success([
            'outlets' => $rows->map(
                fn (Outlet $o) => (new OutletResource($o))->withTotals($totals[$o->id] ?? null)
            ),
        ]);
    }

    /** POST /v1/shops/{shop}/outlets */
    public function store(StoreOutletRequest $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validated();
        $data['phones'] = $this->cleanPhones($data['phones'] ?? []);
        $data['created_by'] = $request->user()->id;

        $outlet = $shop->outlets()->create($data);

        return $this->created(['outlet' => new OutletResource($outlet)]);
    }

    /** GET /v1/shops/{shop}/outlets/{outlet} */
    public function show(Request $request, Shop $shop, Outlet $outlet): JsonResponse
    {
        $this->authorizeShop($request, $shop);
        $this->ensureBelongs($shop, $outlet);

        return $this->success([
            'outlet' => (new OutletResource($outlet))->withTotals($this->outlets->totalsFor($outlet)),
        ]);
    }

    /** PUT /v1/shops/{shop}/outlets/{outlet} */
    public function update(UpdateOutletRequest $request, Shop $shop, Outlet $outlet): JsonResponse
    {
        $this->authorizeShop($request, $shop);
        $this->ensureBelongs($shop, $outlet);

        $data = $request->validated();
        if (array_key_exists('phones', $data)) {
            $data['phones'] = $this->cleanPhones($data['phones'] ?? []);
        }
        $outlet->update($data);

        return $this->success([
            'outlet' => (new OutletResource($outlet->refresh()))->withTotals($this->outlets->totalsFor($outlet)),
        ], __('api.updated'));
    }

    /** DELETE /v1/shops/{shop}/outlets/{outlet} — soft delete, daftar saqlanib qoladi. */
    public function destroy(Request $request, Shop $shop, Outlet $outlet): JsonResponse
    {
        $this->authorizeShop($request, $shop);
        $this->ensureBelongs($shop, $outlet);

        $outlet->delete();

        return $this->deleted();
    }

    /** POST /v1/shops/{shop}/outlets/{outlet}/image */
    public function uploadImage(Request $request, Shop $shop, Outlet $outlet): JsonResponse
    {
        $this->authorizeShop($request, $shop);
        $this->ensureBelongs($shop, $outlet);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($outlet->image_path) {
            Storage::disk('public')->delete($outlet->image_path);
        }

        $path = $request->file('image')->store('outlets', 'public');
        $outlet->update(['image_path' => $path]);

        return $this->success([
            'outlet' => (new OutletResource($outlet->refresh()))->withTotals($this->outlets->totalsFor($outlet)),
        ]);
    }

    /** DELETE /v1/shops/{shop}/outlets/{outlet}/image */
    public function deleteImage(Request $request, Shop $shop, Outlet $outlet): JsonResponse
    {
        $this->authorizeShop($request, $shop);
        $this->ensureBelongs($shop, $outlet);

        if ($outlet->image_path) {
            Storage::disk('public')->delete($outlet->image_path);
            $outlet->update(['image_path' => null]);
        }

        return $this->success([
            'outlet' => (new OutletResource($outlet->refresh()))->withTotals($this->outlets->totalsFor($outlet)),
        ]);
    }

    private function ensureBelongs(Shop $shop, Outlet $outlet): void
    {
        abort_if($outlet->shop_id !== $shop->id, 404, __('api.errors.not_found'));
    }

    /**
     * Bo'sh va takror raqamlar tashlanadi, 3 tagacha qoladi.
     *
     * @param  array<int,string|null>  $phones
     * @return array<int,string>
     */
    private function cleanPhones(array $phones): array
    {
        $clean = [];
        foreach ($phones as $p) {
            $p = trim((string) $p);
            if ($p !== '' && ! in_array($p, $clean, true)) {
                $clean[] = $p;
            }
        }

        return array_slice($clean, 0, Outlet::MAX_PHONES);
    }
}
