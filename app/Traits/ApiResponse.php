<?php

namespace App\Traits;

use App\Support\ApiMeta;
use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, ?string $message = null, int $code = 200, array $meta = []): JsonResponse
    {
        $meta = $this->withUserType($meta);

        $response = [
            'success' => true,
            'message' => $message ?? __('api.success'),
            'data' => $data,
        ];

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message ?? __('api.created'), 201);
    }

    protected function error(?string $message = null, int $code = 400, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message ?? __('api.errors.generic'),
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        $meta = $this->withUserType([]);

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }

    protected function deleted(?string $message = null): JsonResponse
    {
        return $this->success(null, $message ?? __('api.deleted'));
    }

    protected function paginated($paginator, ?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message ?? __('api.success'),
            'data' => $paginator->items(),
            'meta' => $this->withUserType([
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ]),
        ]);
    }

    /**
     * Har bir muvaffaqiyatli javobga joriy foydalanuvchi rolini (user_type) qo'shadi.
     * Auth bo'lmagan (public) so'rovlarda hech narsa qo'shilmaydi.
     */
    private function withUserType(array $meta): array
    {
        return ApiMeta::withUserType($meta);
    }
}
