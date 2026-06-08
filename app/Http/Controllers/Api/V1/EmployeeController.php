<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ShopUserType;
use App\Http\Requests\ConfirmEmployeeRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeePermissionsRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Shop;
use App\Services\EmployeeService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class EmployeeController extends BaseShopController
{
    public function __construct(
        private readonly EmployeeService $employees,
        private readonly PlanLimitService $limits,
    ) {}

    public function index(Request $request, Shop $shop): JsonResponse
    {
        $owner = $this->authorizeOwner($request, $shop);

        return $this->success([
            'employees' => EmployeeResource::collection($this->employees->listForShop($shop)),
            'meta' => $this->seatMeta($owner),
        ]);
    }

    public function store(StoreEmployeeRequest $request, Shop $shop): JsonResponse
    {
        $owner = $this->authorizeOwner($request, $shop);
        $data = $request->validated();

        try {
            $result = $this->employees->startInvite(
                $owner,
                $shop,
                $data['name'],
                $data['phone'],
                $data['password'],
            );
        } catch (RuntimeException $e) {
            return $this->employeeError($e->getMessage());
        }

        return $this->success([
            'requires_code' => true,
            'phone' => $data['phone'],
            'is_paid' => $result['is_paid'],
            'price_usd' => $result['price_usd'],
            'price_local' => $result['price_local'],
            'friday_discount' => $result['friday_discount'],
        ], __('api.employees.code_sent'));
    }

    public function confirm(ConfirmEmployeeRequest $request, Shop $shop): JsonResponse
    {
        $owner = $this->authorizeOwner($request, $shop);
        $data = $request->validated();

        try {
            $pivot = $this->employees->confirm($owner, $shop, $data['phone'], $data['code']);
        } catch (RuntimeException $e) {
            return $this->employeeError($e->getMessage());
        }

        return $this->created([
            'employee' => new EmployeeResource($pivot),
        ], __('api.employees.created'));
    }

    public function updatePermissions(UpdateEmployeePermissionsRequest $request, Shop $shop, string $employee): JsonResponse
    {
        $this->authorizeOwner($request, $shop);

        $pivot = $this->employees->updatePermissions($shop, $employee, $request->validated()['permissions']);

        return $this->success([
            'employee' => new EmployeeResource($pivot),
        ], __('api.updated'));
    }

    public function destroy(Request $request, Shop $shop, string $employee): JsonResponse
    {
        $this->authorizeOwner($request, $shop);

        $this->employees->remove($shop, $employee);

        return $this->deleted();
    }

    // ─── Yordamchilar ──────────────────────────────────────────────────────────

    private function authorizeOwner(Request $request, Shop $shop): \App\Models\User
    {
        $user = $request->user();
        $pivot = $user->userShops()->where('shop_id', $shop->id)->first();

        if (! $pivot || $pivot->user_type !== ShopUserType::Owner) {
            abort(403, __('api.errors.forbidden_owner_only'));
        }

        return $user;
    }

    private function seatMeta(\App\Models\User $owner): array
    {
        return [
            'limit' => $this->limits->info($owner, 'employees'),
            'has_free_slot' => $this->employees->hasFreeSlot($owner),
            'seat_price_usd' => $this->employees->seatPriceUsd(),
            'seat_price_local' => $this->employees->seatPriceLocal(),
            'base_price_usd' => $this->employees->basePriceUsd(),
            'friday_discount' => $this->employees->isFridayDiscountActive(),
            'friday_discount_percent' => $this->employees->fridayDiscountPercent(),
        ];
    }

    private function employeeError(string $key): JsonResponse
    {
        $map = [
            'phone_taken' => [__('api.employees.phone_taken'), 422],
            'insufficient_balance' => [__('api.errors.insufficient_balance'), 402],
            'invalid_code' => [__('api.auth.invalid_code'), 422],
            'invite_expired' => [__('api.employees.invite_expired'), 422],
        ];

        [$message, $status] = $map[$key] ?? [__('api.errors.generic'), 400];

        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $key,
        ], $status);
    }
}
