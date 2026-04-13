<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\BusinessType;
use App\Models\CustomBusinessType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomBusinessTypeController extends Controller
{
    /**
     * Barcha custom kategoriyalar — foydalanish soniga ko'ra tartiblab.
     * Admin panel yoki ichki API uchun mo'ljallangan.
     */
    public function index(Request $request): JsonResponse
    {
        $stats = CustomBusinessType::query()
            ->selectRaw('LOWER(TRIM(name)) as normalized_name, COUNT(*) as usage_count, MAX(name) as display_name, MAX(created_at) as last_used_at')
            ->groupByRaw('LOWER(TRIM(name))')
            ->orderByDesc('usage_count')
            ->paginate(50);

        return $this->success([
            'custom_types' => $stats->map(fn ($row) => [
                'name'        => $row->display_name,
                'usage_count' => (int) $row->usage_count,
                'last_used_at' => $row->last_used_at,
            ])->values(),
            'meta' => [
                'total'        => $stats->total(),
                'current_page' => $stats->currentPage(),
                'last_page'    => $stats->lastPage(),
            ],
        ]);
    }

    /**
     * Custom kategoriyani rasmiy BusinessType sifatida tizimga qo'shish.
     * Admin shu endpointdan foydalanadi: keyin hamma foydalanuvchilarga ko'rinadi.
     */
    public function promote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name_uz'       => ['required', 'string', 'max:100'],
            'name_uz_cyrl'  => ['nullable', 'string', 'max:100'],
            'name_ru'       => ['nullable', 'string', 'max:100'],
            'name_kk'       => ['nullable', 'string', 'max:100'],
            'name_ky'       => ['nullable', 'string', 'max:100'],
            'name_tr'       => ['nullable', 'string', 'max:100'],
            'key'           => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', Rule::unique('business_types', 'key')],
            'icon'          => ['nullable', 'string', 'max:10'],
            'color'         => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order'    => ['nullable', 'integer'],
        ]);

        $businessType = BusinessType::create([
            'key'          => $validated['key'],
            'name_uz'      => $validated['name_uz'],
            'name_uz_cyrl' => $validated['name_uz_cyrl'] ?? $validated['name_uz'],
            'name_ru'      => $validated['name_ru'] ?? $validated['name_uz'],
            'name_kk'      => $validated['name_kk'] ?? null,
            'name_ky'      => $validated['name_ky'] ?? null,
            'name_tr'      => $validated['name_tr'] ?? null,
            'icon'         => $validated['icon'] ?? '🏢',
            'color'        => $validated['color'] ?? '#607D8B',
            'sort_order'   => $validated['sort_order'] ?? 99,
            'is_active'    => true,
        ]);

        return $this->created([
            'business_type' => [
                'id'      => $businessType->id,
                'key'     => $businessType->key,
                'name_uz' => $businessType->name_uz,
            ],
        ], "Kategoriya muvaffaqiyatli tizimga qo'shildi");
    }
}
