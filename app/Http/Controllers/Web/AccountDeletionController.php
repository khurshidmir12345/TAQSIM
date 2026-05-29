<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Public account deletion flow (Google Play / App Store compliance).
 *
 * Foydalanuvchi ilova o'rnatmasdan o'z akkauntini o'chira oladi.
 *  1) GET  /delete-account              → form ni ko'rsatadi
 *  2) POST /delete-account/send-code    → telefonga 4-xonali SMS kod yuboradi
 *  3) POST /delete-account/confirm      → kodni tekshiradi va akkauntni o'chiradi
 */
class AccountDeletionController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly SmsService $smsService,
    ) {}

    public function show(): View
    {
        return view('account.delete');
    }

    public function sendCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^\+?\d{9,15}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Telefon raqami noto\'g\'ri formatda.',
            ], 422);
        }

        $phone = $this->normalizePhone($request->input('phone'));

        $exists = User::where('phone', $phone)->exists();
        if (! $exists) {
            return response()->json([
                'success' => false,
                'message' => 'Bu telefon raqamiga ulangan akkaunt topilmadi.',
            ], 404);
        }

        try {
            $record = $this->otpService->generate($phone);
            $this->smsService->sendOtp($phone, $record->code);
        } catch (\Throwable $e) {
            Log::warning('AccountDeletion sendCode failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Kod yuborishda xatolik. Iltimos, biroz keyin qayta urinib ko\'ring.',
            ], 500);
        }

        return response()->json([
            'success'    => true,
            'message'    => 'Tasdiqlash kodi yuborildi.',
            'expires_in' => 120,
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'regex:/^\+?\d{9,15}$/'],
            'code'  => ['required', 'string', 'digits:4'],
            'agree' => ['accepted'],
        ], [
            'agree.accepted' => 'Akkauntni o\'chirish shartlariga rozilik bering.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->input('phone'));
        $code  = $request->input('code');

        $verified = $this->otpService->validate($phone, $code);
        if (! $verified) {
            return response()->json([
                'success' => false,
                'message' => 'Kod noto\'g\'ri yoki muddati o\'tgan.',
            ], 422);
        }

        $user = User::where('phone', $phone)->first();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Akkaunt topilmadi.',
            ], 404);
        }

        $userId = $user->id;

        try {
            DB::transaction(function () use ($user) {
                $user->tokens()->delete();
                $user->delete();
            });
        } catch (\Throwable $e) {
            Log::error('AccountDeletion deletion failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Akkauntni o\'chirib bo\'lmadi. Yordam uchun: support@taqseem.uz',
            ], 500);
        }

        Log::info('Account deleted via public web form', [
            'user_id' => $userId,
            'phone'   => $phone,
            'ip'      => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Akkaunt va unga bog\'liq barcha ma\'lumotlar o\'chirildi.',
        ]);
    }

    /**
     * "+998 90 123 45 67" → "+998901234567"
     * "998901234567"      → "+998901234567"
     */
    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);

        if (str_starts_with($digits, '998')) {
            return '+' . $digits;
        }

        // Foydalanuvchi to'g'ridan-to'g'ri "+998..." yozgan bo'lsa
        if (str_starts_with($raw, '+')) {
            return '+' . $digits;
        }

        return '+' . $digits;
    }
}
