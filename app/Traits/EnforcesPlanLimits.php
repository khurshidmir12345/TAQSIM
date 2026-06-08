<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait EnforcesPlanLimits
{
    /**
     * Tarif limiti to'lganda standart 403 javob (frontend `code` orqali
     * upgrade UI ko'rsatadi).
     *
     * @param  array{limit:int|null, used:int, unlimited:bool, remaining:int|null}  $info
     */
    protected function planLimitResponse(array $info, string $resource): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('api.errors.plan_limit_reached'),
            'code' => 'plan_limit_reached',
            'data' => array_merge(['resource' => $resource], $info),
        ], 403);
    }
}
