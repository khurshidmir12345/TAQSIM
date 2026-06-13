<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceService $devices,
    ) {}

    /**
     * GET /v1/auth/devices
     * Joriy foydalanuvchining barcha aktiv qurilmalari (joriy belgili).
     */
    public function index(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()?->getKey();

        return $this->success([
            'devices' => $this->devices->list($request->user(), $currentTokenId),
        ]);
    }

    /**
     * DELETE /v1/auth/devices/{device}
     * Tanlangan qurilma sessiyasini chiqaradi (joriy qurilmani ham).
     */
    public function destroy(Request $request, string $device): JsonResponse
    {
        $tokenId = filter_var($device, FILTER_VALIDATE_INT);

        if ($tokenId === false) {
            return $this->error(__('api.errors.not_found'), 404);
        }

        $revoked = $this->devices->revoke($request->user(), (int) $tokenId);

        if (! $revoked) {
            return $this->error(__('api.errors.not_found'), 404);
        }

        return $this->deleted(__('api.auth.device_revoked'));
    }
}
