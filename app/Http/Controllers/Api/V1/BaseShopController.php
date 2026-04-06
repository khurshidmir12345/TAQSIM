<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Traits\Filterable;
use Illuminate\Http\Request;

abstract class BaseShopController extends Controller
{
    use Filterable;

    protected function authorizeShop(Request $request, Shop $shop): void
    {
        $exists = $request->user()->userShops()->where('shop_id', $shop->id)->exists();

        if (! $exists) {
            abort(403, __('api.errors.forbidden_shop_bakery'));
        }
    }
}
