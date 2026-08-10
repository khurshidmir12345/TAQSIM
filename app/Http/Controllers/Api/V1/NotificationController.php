<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * GET /v1/notifications
     * Ro'yxat (eng yangisi tepada) + o'qilmaganlar soni.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = AppNotification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(self::PER_PAGE);

        return $this->success([
            'notifications' => $notifications->getCollection()->map(fn (AppNotification $n): array => [
                'id' => $n->id,
                'category' => $n->category->value,
                'title' => $n->title,
                'body' => $n->body,
                'data' => $n->data,
                'is_read' => $n->isRead(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->all(),
            'unread_count' => $this->unreadCount($request),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * GET /v1/notifications/unread-count
     * Nav bar'dagi belgi uchun — yengil so'rov.
     */
    public function unread(Request $request): JsonResponse
    {
        return $this->success(['unread_count' => $this->unreadCount($request)]);
    }

    /** POST /v1/notifications/{notification}/read */
    public function markRead(Request $request, string $notification): JsonResponse
    {
        $record = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $notification)
            ->first();

        if (! $record) {
            return $this->error(__('api.errors.not_found'), 404);
        }

        if (! $record->isRead()) {
            $record->update(['read_at' => now()]);
        }

        return $this->success(['unread_count' => $this->unreadCount($request)]);
    }

    /** POST /v1/notifications/read-all */
    public function markAllRead(Request $request): JsonResponse
    {
        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return $this->success(['unread_count' => 0]);
    }

    /** DELETE /v1/notifications/{notification} */
    public function destroy(Request $request, string $notification): JsonResponse
    {
        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $notification)
            ->delete();

        return $this->success(['unread_count' => $this->unreadCount($request)]);
    }

    /**
     * GET /v1/notifications/preferences
     * Sozlanmagan bo'lsa `true` qaytadi — yangi foydalanuvchi push oladi.
     */
    public function preferences(Request $request): JsonResponse
    {
        return $this->success(['preferences' => $this->currentPreferences($request)]);
    }

    /**
     * PUT /v1/notifications/preferences
     *
     * Yagona tugma — `enabled`. Eski mobil versiyalar tur bo'yicha kalitlar
     * ham yuboradi: ular rad etilmaydi, lekin hech narsaga ta'sir qilmaydi.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            ...array_fill_keys(
                NotificationCategory::legacyPreferenceKeys(),
                ['sometimes', 'boolean'],
            ),
        ]);

        if (array_key_exists('enabled', $validated)) {
            $user = $request->user();
            $prefs = is_array($user->notification_prefs) ? $user->notification_prefs : [];
            $prefs['enabled'] = (bool) $validated['enabled'];

            $user->update(['notification_prefs' => $prefs]);
        }

        return $this->success(['preferences' => $this->currentPreferences($request)]);
    }

    /**
     * POST /v1/notifications/push-token
     * FCM tokenini joriy qurilma sessiyasiga bog'laydi.
     */
    public function registerPushToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'push_token' => ['required', 'string', 'max:512'],
            'platform' => ['sometimes', 'string', Rule::in(['ios', 'android', 'web'])],
        ]);

        $tokenId = $request->user()->currentAccessToken()?->getKey();

        if (! $tokenId) {
            return $this->error(__('api.errors.generic'), 400);
        }

        // Bir token faqat bitta qurilmada bo'lsin — ilova boshqa hisobga
        // kirsa, eski bog'lanish uzilishi kerak, aks holda push eski
        // foydalanuvchi nomidan kelaverardi.
        UserDevice::query()
            ->where('push_token', $validated['push_token'])
            ->where('token_id', '!=', $tokenId)
            ->update(['push_token' => null]);

        UserDevice::query()
            ->where('user_id', $request->user()->id)
            ->where('token_id', $tokenId)
            ->update([
                'push_token' => $validated['push_token'],
                'push_token_updated_at' => now(),
                ...(isset($validated['platform']) ? ['platform' => $validated['platform']] : []),
            ]);

        return $this->success(null, __('api.success'));
    }

    /**
     * Eski kalitlar haqiqiy holatni aks ettirib qaytadi: eslatuvchi turlar
     * umumiy tugmaga ergashadi, majburiy turlar esa doim yoqiq — foydalanuvchi
     * qo'lidagi eski ilovada tugmalar haqiqatga zid ko'rinmasin.
     *
     * @return array<string,bool>
     */
    private function currentPreferences(Request $request): array
    {
        $prefs = $request->user()->notification_prefs;
        $prefs = is_array($prefs) ? $prefs : [];

        $enabled = ($prefs['enabled'] ?? true) !== false;

        $result = ['enabled' => $enabled];

        foreach (NotificationCategory::cases() as $category) {
            if (in_array($category->value, NotificationCategory::legacyPreferenceKeys(), true)) {
                $result[$category->value] = $category->isOptional() ? $enabled : true;
            }
        }

        return $result;
    }

    private function unreadCount(Request $request): int
    {
        return AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->unread()
            ->count();
    }
}
