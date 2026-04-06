<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessTypeResource;
use App\Models\BusinessType;
use Illuminate\Http\JsonResponse;

class BusinessTypeController extends Controller
{
    /**
     * GET /v1/business-types
     * Returns all active business types with their full terminology.
     * Public — no auth required (needed during shop creation wizard).
     */
    public function index(): JsonResponse
    {
        $types = BusinessType::active()->get();

        return $this->success([
            'business_types' => BusinessTypeResource::collection($types),
        ]);
    }

    /**
     * GET /v1/business-types/{key}
     * Returns a single business type by its key (e.g. 'bakery').
     */
    public function show(string $key): JsonResponse
    {
        $type = BusinessType::where('key', $key)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->success([
            'business_type' => new BusinessTypeResource($type),
        ]);
    }
}
