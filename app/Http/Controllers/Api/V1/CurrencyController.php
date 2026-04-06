<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    public function index(): JsonResponse
    {
        $currencies = Currency::query()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        return $this->success([
            'currencies' => CurrencyResource::collection($currencies),
        ]);
    }
}
