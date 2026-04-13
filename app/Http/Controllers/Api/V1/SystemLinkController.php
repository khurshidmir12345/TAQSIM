<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SystemLink;
use Illuminate\Http\JsonResponse;

class SystemLinkController extends Controller
{
    /**
     * GET /v1/system-links
     * Returns all active system links (terms, privacy, etc.)
     * Public endpoint — no auth required.
     */
    public function index(): JsonResponse
    {
        $links = SystemLink::active()
            ->orderBy('type')
            ->get(['type', 'name', 'url']);

        return $this->success([
            'links' => $links->map(fn($l) => [
                'type' => $l->type,
                'name' => $l->name,
                'url'  => $l->url,
            ])->values(),
        ]);
    }
}
