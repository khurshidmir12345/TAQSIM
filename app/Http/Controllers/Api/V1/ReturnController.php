<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreReturnRequest;
use App\Http\Resources\BreadReturnResource;
use App\Models\BreadReturn;
use App\Models\Production;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnController extends BaseShopController
{
    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $query = $shop->breadReturns()
            ->with(['breadCategory', 'production'])
            ->getQuery();

        if ($request->has('date')) {
            $query->whereDate('date', $request->query('date'));
        }

        $this->applySorting($query, $request, 'date', 'desc');

        if ($request->boolean('paginate', false)) {
            $perPage = min((int) $request->query('per_page', 20), 100);
            $paginator = $query->paginate($perPage);
            $requestObj = $request;
            $collection = $paginator->getCollection();
            $mapped = $collection->map(
                fn ($r) => (new BreadReturnResource($r))->toArray($requestObj)
            );
            $paginator->setCollection($mapped);

            return $this->paginated($paginator);
        }

        return $this->success([
            'returns' => BreadReturnResource::collection($query->get()),
        ]);
    }

    public function store(StoreReturnRequest $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validated();

        $production = Production::query()->findOrFail($data['production_id']);

        if ($production->shop_id !== $shop->id) {
            abort(404);
        }

        if ($production->bread_category_id !== $data['bread_category_id']) {
            return $this->error(__('api.errors.return_production_mismatch'), 422);
        }

        if ($production->date->toDateString() !== $data['date']) {
            return $this->error(__('api.errors.return_production_mismatch'), 422);
        }

        $data['total_amount'] = $data['quantity'] * $data['price_per_unit'];
        $data['created_by'] = $request->user()->id;

        $return = $shop->breadReturns()->create($data);
        $return->load(['breadCategory', 'production']);

        return $this->created([
            'return' => new BreadReturnResource($return),
        ]);
    }

    public function destroy(Request $request, Shop $shop, BreadReturn $return): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $return->delete();

        return $this->deleted();
    }
}
