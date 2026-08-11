<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AppUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    public function __construct(
        private readonly AppUpdateService $appUpdate,
    ) {}

    /**
     * GET /v1/app-version?platform=android&version=1.2.7
     *
     * Ilova ochilganda chaqiriladi. Ochiq endpoint — tekshiruv login'gacha
     * ham ishlashi kerak.
     */
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['nullable', 'string', 'in:android,ios'],
            'version' => ['nullable', 'string', 'max:32'],
        ]);

        return $this->success(
            $this->appUpdate->check(
                $validated['platform'] ?? null,
                $validated['version'] ?? null,
            )
        );
    }
}
