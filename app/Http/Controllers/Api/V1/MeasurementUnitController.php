<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeasurementUnitResource;
use App\Models\MeasurementUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeasurementUnitController extends Controller
{
    /**
     * GET /v1/measurement-units
     * ?type=ingredient|batch — optional filter
     */
    public function index(Request $request): JsonResponse
    {
        $query = MeasurementUnit::active()->orderBy('sort_order');

        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        $units = $query->get();

        return response()->json([
            'success' => true,
            'data'    => MeasurementUnitResource::collection($units),
        ]);
    }

    /**
     * GET /v1/measurement-units/ingredient
     */
    public function ingredient(): JsonResponse
    {
        $allowed = ['kg', 'l', 'm', 'ta'];
        $units = MeasurementUnit::active()
            ->ingredient()
            ->whereIn('code', $allowed)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => MeasurementUnitResource::collection($units),
        ]);
    }

    /**
     * GET /v1/measurement-units/product
     *
     * Mahsulotlar uchun 4 ta o'lchov birligi: Dona, Kilogram, Litr, Metr.
     * Xom ashyo bilan bir xil jadvalni qayta ishlatamiz (DRY) — chunki
     * mantiq bir xil: shu 4 birlik universal ishlab chiqaruvchi uchun yetarli.
     */
    public function product(): JsonResponse
    {
        $allowed = ['ta', 'kg', 'l', 'm'];
        $units = MeasurementUnit::active()
            ->ingredient()
            ->whereIn('code', $allowed)
            ->orderByRaw("FIELD(code, 'ta', 'kg', 'l', 'm')")
            ->get();

        return response()->json([
            'success' => true,
            'data'    => MeasurementUnitResource::collection($units),
        ]);
    }

    /**
     * GET /v1/measurement-units/batch
     */
    public function batch(): JsonResponse
    {
        $units = MeasurementUnit::active()
            ->batch()
            ->whereIn('code', MeasurementUnit::BATCH_UNIT_CODES)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => MeasurementUnitResource::collection($units),
        ]);
    }
}
