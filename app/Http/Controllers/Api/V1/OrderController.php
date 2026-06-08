<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /** Foydalanuvchi xaridlari/buyurtmalari tarixi — sahifalangan. */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(50, max(5, (int) $request->integer('per_page', 20)));

        $paginator = $request->user()
            ->orders()
            ->with('plan')
            ->paginate($perPage);

        $paginator->through(fn ($order) => (new OrderResource($order))->resolve());

        return $this->paginated($paginator);
    }
}
