<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Shop;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends BaseShopController
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    public function daily(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $date = $request->validate([
            'date' => ['required', 'date'],
        ])['date'];

        $report = $this->reportService->daily($shop, $date);

        return $this->success(['report' => $report]);
    }

    public function range(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $report = $this->reportService->range($shop, $data['from'], $data['to']);

        return $this->success(['report' => $report]);
    }

    /**
     * Statistika sahifasi uchun: kunlik grafik, umumiy summalar va
     * mahsulotning asl tannarxi — bitta so'rovda.
     *
     * Oraliq berilmasa oxirgi 30 kun olinadi.
     */
    public function statistics(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $data = $request->validate([
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'period' => ['sometimes', 'in:all,month,day'],
        ]);

        $to = $data['to'] ?? now()->toDateString();
        // "Barchasi" da boshlanish sanasini ilova bilmaydi — uni server
        // do'kondagi birinchi yozuvga qarab aniqlaydi.
        $from = ($data['period'] ?? null) === 'all'
            ? $this->reportService->earliestActivityDate($shop)
            : $data['from'] ?? now()->subDays(29)->toDateString();

        return $this->success([
            'statistics' => $this->reportService->statistics($shop, $from, $to),
        ]);
    }

    public function summary(Request $request, Shop $shop): JsonResponse
    {
        $this->authorizeShop($request, $shop);

        $summary = $this->reportService->summary($shop);

        return $this->success(['summary' => $summary]);
    }
}
