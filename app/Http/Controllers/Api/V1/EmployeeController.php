<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ShopUserType;
use App\Http\Requests\ConfirmEmployeeRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeePermissionsRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Shop;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class EmployeeController extends BaseShopController
{
    public function __construct(
        private readonly EmployeeService $employees,
    ) {}

    public function index(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeOwner($request, $shop);

        return $this->success([
            'employees' => EmployeeResource::collection($this->employees->listForShop($shop)),
        ]);
    }

    public function store(StoreEmployeeRequest $request, Shop $shop): JsonResponse
    {
        $owner = $this->authorizeOwner($request, $shop);
        $data = $request->validated();

        try {
            $this->employees->startInvite(
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

    private function authorizeOwner(Request $request, Shop $shop): User
    {
        $user = $request->user();
        $pivot = $user->userShops()->where('shop_id', $shop->id)->first();

        if (! $pivot || $pivot->user_type !== ShopUserType::Owner) {
            abort(403, __('api.errors.forbidden_owner_only'));
        }

        return $user;
    }

    private function employeeError(string $key): JsonResponse
    {
        $map = [
            'phone_taken' => [__('api.employees.phone_taken'), 422],
            'invalid_code' => [__('api.auth.invalid_code'), 422],
            'invite_expired' => [__('api.employees.invite_expired'), 422],
        ];

        [$message, $status] = $map[$key] ?? [__('api.errors.generic'), 400];

        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $key,
            'meta' => \App\Support\ApiMeta::withUserType(),
        ], $status);
    }
}
