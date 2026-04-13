<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends BaseShopController
{
    /**
     * Foydalanuvchining onboarding (tutorial) holatini qaytaradi.
     *
     * GET /api/v1/shops/{shop}/onboarding-status
     *
     * Response:
     * {
     *   "data": {
     *     "has_created_product":      bool,
     *     "has_created_raw_material": bool,
     *     "has_created_calculation":  bool,
     *     "has_made_product_income":  bool
     *   }
     * }
     */
    public function status(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        return $this->success([
            'has_created_product'      => $shop->breadCategories()->exists(),
            'has_created_raw_material' => $shop->ingredients()->exists(),
            'has_created_calculation'  => $shop->recipes()->exists(),
            'has_made_product_income'  => $shop->productions()->exists(),
        ]);
    }
}
