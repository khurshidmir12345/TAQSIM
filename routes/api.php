<?php

use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\V1\AppleAuthController;
use App\Http\Controllers\Api\V1\AppVersionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BreadCategoryController;
use App\Http\Controllers\Api\V1\CashController;
use App\Http\Controllers\Api\V1\BusinessTypeController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\CustomBusinessTypeController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\CustomerOrderController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\ExpenseCategoryController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\GoogleAuthController;
use App\Http\Controllers\Api\V1\IngredientController;
use App\Http\Controllers\Api\V1\MeasurementUnitController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\ProductionController;
use App\Http\Controllers\Api\V1\RecipeController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReturnController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\SystemLinkController;
use App\Http\Controllers\Api\V1\TelegramAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'success' => true,
        'message' => __('api.ping'),
        'data' => ['version' => '1.0.0'],
    ]);
});

// ── Telegram Webhook (outside v1, no auth) ──────────────────────────
Route::post('/telegram/webhook/{botToken}', [TelegramWebhookController::class, 'handle']);

Route::prefix('v1')->group(function () {

    // ── Telegram Auth (public) ──────────────────────────────────────────
    Route::post('/auth/telegram/session', [TelegramAuthController::class, 'createSession'])
        ->middleware('throttle:telegram-session');
    Route::get('/auth/telegram/check/{sessionToken}', [TelegramAuthController::class, 'checkSession'])
        ->middleware('throttle:telegram-check');

    // ── Business Types & Measurement Units (public — needed before auth for shop wizard) ──
    Route::get('/business-types', [BusinessTypeController::class, 'index']);
    Route::get('/business-types/{key}', [BusinessTypeController::class, 'show']);
    Route::get('/measurement-units', [MeasurementUnitController::class, 'index']);
    Route::get('/measurement-units/ingredient', [MeasurementUnitController::class, 'ingredient']);
    Route::get('/measurement-units/product', [MeasurementUnitController::class, 'product']);
    Route::get('/measurement-units/batch', [MeasurementUnitController::class, 'batch']);
    Route::get('/currencies', [CurrencyController::class, 'index']);
    Route::get('/system-links', [SystemLinkController::class, 'index']);

    // ── Ilova versiyasi (public — tekshiruv login'gacha ham bo'ladi) ──
    Route::get('/app-version', [AppVersionController::class, 'show']);

    // ── Auth (public) ──────────────────────────────────────────────
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/auth/send-code', [AuthController::class, 'sendCode']);
        Route::post('/auth/register', [AuthController::class, 'register']);
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
        Route::post('/auth/apple', [AppleAuthController::class, 'login']);
        Route::post('/auth/google', [GoogleAuthController::class, 'login']);
    });

    // ── Auth (protected) ───────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'throttle:api', 'blocked'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('/auth/avatar', [AuthController::class, 'uploadAvatar']);
        Route::delete('/auth/avatar', [AuthController::class, 'deleteAvatar']);
        Route::put('/auth/password', [AuthController::class, 'changePassword']);

        // ── Telefon raqamni almashtirish (SMS tasdiqlash bilan) ─────
        // Kod tasdiqlanmaguncha eski raqam o'zgarmaydi.
        Route::post('/auth/phone/send-code', [AuthController::class, 'sendPhoneChangeCode'])
            ->middleware('throttle:auth');
        Route::post('/auth/phone', [AuthController::class, 'changePhone'])
            ->middleware('throttle:auth');

        Route::delete('/auth/account', [AuthController::class, 'deleteAccount']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // ── Qurilmalar (multi-device sessiya boshqaruvi) ────────────
        Route::get('/auth/devices', [DeviceController::class, 'index']);
        Route::delete('/auth/devices/{device}', [DeviceController::class, 'destroy']);

        // ── Bildirishnomalar ───────────────────────────────────────
        // Diqqat: `preferences`, `unread-count`, `read-all` va `push-token`
        // {notification} parametridan OLDIN turishi shart, aks holda ular
        // ID sifatida ushlanib qolardi.
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unread']);
        Route::get('/notifications/preferences', [NotificationController::class, 'preferences']);
        Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences']);
        Route::post('/notifications/push-token', [NotificationController::class, 'registerPushToken']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

        // ── Telegram Connect (mavjud foydalanuvchiga bog'lash) ──────
        Route::post('/auth/telegram/connect-session', [TelegramAuthController::class, 'createConnectSession']);
        Route::get('/auth/telegram/connect-status/{sessionToken}', [TelegramAuthController::class, 'connectStatus']);

        // ── Shops ──────────────────────────────────────────────────
        Route::apiResource('shops', ShopController::class);

        // ── Custom Business Types (admin statistika + promote) ──────
        Route::get('/custom-business-types', [CustomBusinessTypeController::class, 'index']);
        Route::post('/custom-business-types/promote', [CustomBusinessTypeController::class, 'promote']);

        Route::prefix('shops/{shop}')->group(function () {
            Route::apiResource('bread-categories', BreadCategoryController::class)
                ->middleware('shop.perm:manage_products');
            Route::apiResource('ingredients', IngredientController::class)
                ->middleware('shop.perm:manage_recipes');
            Route::apiResource('recipes', RecipeController::class)
                ->middleware('shop.perm:manage_recipes');
            Route::apiResource('productions', ProductionController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->middleware('shop.perm:manage_production');
            Route::apiResource('returns', ReturnController::class)
                ->only(['index', 'store', 'destroy'])
                ->middleware('shop.perm:manage_sales');
            Route::apiResource('expenses', ExpenseController::class)
                ->middleware('shop.perm:manage_expenses');
            // ── Kassa ────────────────────────────────────────────────
            // Aniq yo'llar {entry} parametridan OLDIN turishi shart, aks holda
            // "settings" yozuv id'si deb qabul qilinardi.
            Route::prefix('cash')->middleware('shop.perm:manage_expenses')->group(function () {
                Route::get('/', [CashController::class, 'index']);
                Route::get('settings', [CashController::class, 'settings']);
                Route::put('settings', [CashController::class, 'updateSettings']);
                Route::get('income-categories', [CashController::class, 'incomeCategories']);
                Route::post('/', [CashController::class, 'store']);
                Route::put('{entry}', [CashController::class, 'update']);
                Route::delete('{entry}', [CashController::class, 'destroy']);
            });

            Route::get('expense-categories', [ExpenseCategoryController::class, 'index'])
                ->middleware('shop.perm:manage_expenses');
            Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])
                ->middleware('shop.perm:manage_expenses');
            Route::put('expense-categories/{category}', [ExpenseCategoryController::class, 'update'])
                ->middleware('shop.perm:manage_expenses');
            Route::delete('expense-categories/{category}', [ExpenseCategoryController::class, 'destroy'])
                ->middleware('shop.perm:manage_expenses');

            Route::apiResource('customers', CustomerController::class)
                ->only(['index', 'store', 'show', 'update', 'destroy'])
                ->middleware('shop.perm:manage_orders,read');
            Route::prefix('customer-orders')->middleware('shop.perm:manage_orders,read')->group(function () {
                Route::get('/', [CustomerOrderController::class, 'index']);
                Route::post('/', [CustomerOrderController::class, 'store']);
                Route::get('{customer_order}', [CustomerOrderController::class, 'show']);
                Route::put('{customer_order}', [CustomerOrderController::class, 'update']);
                Route::delete('{customer_order}', [CustomerOrderController::class, 'destroy']);
                Route::post('{customer_order}/payments', [CustomerOrderController::class, 'storePayment']);
                Route::post('{customer_order}/deliver', [CustomerOrderController::class, 'deliver']);
                Route::post('{customer_order}/cancel', [CustomerOrderController::class, 'cancel']);
            });

            Route::middleware('shop.perm:view_reports,read')->group(function () {
                Route::get('reports/daily', [ReportController::class, 'daily']);
                Route::get('reports/range', [ReportController::class, 'range']);
                Route::get('reports/summary', [ReportController::class, 'summary']);
                Route::get('reports/statistics', [ReportController::class, 'statistics']);
            });

            // ── Xodimlar (faqat owner — 100% bepul) ─────────────────
            Route::middleware('shop.perm:owner')->group(function () {
                Route::get('employees', [EmployeeController::class, 'index']);
                Route::post('employees', [EmployeeController::class, 'store']);
                Route::post('employees/confirm', [EmployeeController::class, 'confirm']);
                Route::put('employees/{employee}/permissions', [EmployeeController::class, 'updatePermissions']);
                Route::delete('employees/{employee}', [EmployeeController::class, 'destroy']);
            });

            // Tutorial / Onboarding status
            Route::get('onboarding-status', [OnboardingController::class, 'status']);
        });
    });
});
