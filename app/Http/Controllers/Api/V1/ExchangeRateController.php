<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;

class ExchangeRateController extends Controller
{
    public function __construct(
        private readonly ExchangeRateService $exchange,
    ) {}

    /** Joriy USD→UZS kursi. */
    public function show(): JsonResponse
    {
        return $this->success([
            'base_code' => 'USD',
            'quote_code' => 'UZS',
            'rate' => $this->exchange->usdToUzs(),
        ]);
    }
}
