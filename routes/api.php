<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BreadCategoryController;
use App\Http\Controllers\Api\V1\BusinessTypeController;
use App\Http\Controllers\Api\V1\CustomBusinessTypeController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\ExpenseCategoryController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\IngredientController;
use App\Http\Controllers\Api\V1\MeasurementUnitController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\ProductionController;
use App\Http\Controllers\Api\V1\RecipeController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReturnController;
use App\Http\Controllers\Api\V1\ShopController;
use App\Http\Controllers\Api\V1\SystemLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'success' => true,
        'message' => __('api.ping'),
        'data' => ['version' => '1.0.0'],
    ]);
});

Route::prefix('v1')->group(function () {

    // ── Business Types & Measurement Units (public — needed before auth for shop wizard) ──
    Route::get('/business-types',              [BusinessTypeController::class, 'index']);
    Route::get('/business-types/{key}',        [BusinessTypeController::class, 'show']);
    Route::get('/measurement-units',           [MeasurementUnitController::class, 'index']);
    Route::get('/measurement-units/ingredient',[MeasurementUnitController::class, 'ingredient']);
    Route::get('/measurement-units/batch',     [MeasurementUnitController::class, 'batch']);
    Route::get('/currencies',                  [CurrencyController::class, 'index']);
    Route::get('/system-links',                [SystemLinkController::class, 'index']);

    // ── Auth (public) ──────────────────────────────────────────────
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/auth/send-code', [AuthController::class, 'sendCode']);
        Route::post('/auth/register',  [AuthController::class, 'register']);
        Route::post('/auth/login',     [AuthController::class, 'login']);
    });

    // ── Auth (protected) ───────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('/auth/me',            [AuthController::class, 'me']);
        Route::put('/auth/profile',       [AuthController::class, 'updateProfile']);
        Route::post('/auth/avatar',       [AuthController::class, 'uploadAvatar']);
        Route::put('/auth/password',      [AuthController::class, 'changePassword']);
        Route::delete('/auth/account',    [AuthController::class, 'deleteAccount']);
        Route::post('/auth/logout',       [AuthController::class, 'logout']);

        // ── Shops ──────────────────────────────────────────────────
        Route::apiResource('shops', ShopController::class);

        // ── Custom Business Types (admin statistika + promote) ──────
        Route::get('/custom-business-types',          [CustomBusinessTypeController::class, 'index']);
        Route::post('/custom-business-types/promote', [CustomBusinessTypeController::class, 'promote']);

        Route::prefix('shops/{shop}')->group(function () {
            Route::apiResource('bread-categories', BreadCategoryController::class);
            Route::apiResource('ingredients',      IngredientController::class);
            Route::apiResource('recipes',          RecipeController::class);
            Route::apiResource('productions',      ProductionController::class)
                ->only(['index', 'store', 'update', 'destroy']);
            Route::apiResource('returns',          ReturnController::class)
                ->only(['index', 'store', 'destroy']);
            Route::apiResource('expenses',         ExpenseController::class);
            Route::get('expense-categories', [ExpenseCategoryController::class, 'index']);
            Route::post('expense-categories', [ExpenseCategoryController::class, 'store']);

            Route::get('reports/daily',   [ReportController::class, 'daily']);
            Route::get('reports/range',   [ReportController::class, 'range']);
            Route::get('reports/summary', [ReportController::class, 'summary']);

            // Tutorial / Onboarding status
            Route::get('onboarding-status', [OnboardingController::class, 'status']);
        });
    });
});
