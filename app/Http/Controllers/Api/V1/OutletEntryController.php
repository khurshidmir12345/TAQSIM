<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreOutletEntryRequest;
use App\Http\Resources\OutletEntryResource;
use App\Http\Resources\OutletResource;
use App\Models\Outlet;
use App\Models\OutletEntry;
use App\Models\Shop;
use App\Services\OutletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Do'kon daftari: berildi / qaytdi / to'landi. */
class OutletEntryController extends BaseShopController
{
    public function __construct(private readonly OutletService $outlets) {}

    /** GET /v1/shops/{shop}/outlets/{outlet}/entries?from=&to= */
    public function index(Request $request, Shop $shop, Outlet $outlet): JsonResponse
    {
        $this->authorizeShop($request, $shop);
        abort_if($outlet->shop_id !== $shop->id, 404, __('api.errors.not_found'));

        $data = $request->validate([
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
        ]);

        $entries = $this->outlets->entries($outlet, $data['from'] ?? null, $data['to'] ?? null);

        return $this->success([
            'outlet' => (new OutletResource($outlet))->withTotals($this->outlets->totalsFor($outlet)),
            'entries' => OutletEntryResource::collection($entries),
        ]);
    }

    /** POST /v1/shops/{shop}/outlets/{outlet}/entries */
    public function store(StoreOutletEntryRequest $request, Shop $shop, Outlet $outlet): JsonResponse
    {
        $this->authorizeShop($request, $shop);
        abort_if($outlet->shop_id !== $shop->id, 404, __('api.errors.not_found'));

        $entry = $this->outlets->createEntry($shop, $outlet, $request->validated(), $request->user()->id);
        $entry->load('items.breadCategory');

        return $this->created([
            'entry' => new OutletEntryResource($entry),
            'outlet' => (new OutletResource($outlet))->withTotals($this->outlets->totalsFor($outlet)),
        ]);
    }

    /** DELETE /v1/shops/{shop}/outlets/{outlet}/entries/{entry} */
    public function destroy(Request $request, Shop $shop, Outlet $outlet, OutletEntry $entry): JsonResponse
    {
        $this->authorizeShop($request, $shop);
        abort_if($outlet->shop_id !== $shop->id || $entry->outlet_id !== $outlet->id, 404, __('api.errors.not_found'));

        $this->outlets->deleteEntry($entry);

        return $this->success([
            'outlet' => (new OutletResource($outlet))->withTotals($this->outlets->totalsFor($outlet)),
        ], __('api.deleted'));
    }
}
