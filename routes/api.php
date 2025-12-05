<?php

use App\Http\Controllers\Api\AIGenerationController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandKitController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CampaignTemplateController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\UserGeneratedImageController;
use App\Http\Controllers\Api\VideoJobController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// API Version 1
Route::prefix('v1')->group(function () {

    // ============================================
    // PUBLIC ROUTES (No Authentication Required)
    // ============================================

    // Authentication
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    // Public Campaigns
    Route::prefix('campaigns')->group(function () {
        Route::get('/', [CampaignController::class, 'index']);
        Route::get('/popular', [CampaignController::class, 'popular']);
        Route::get('/recent', [CampaignController::class, 'recent']);
        Route::get('/trending', [StatisticsController::class, 'trending']);
        Route::get('/search', [CampaignController::class, 'search']);
        Route::get('/{slug}', [CampaignController::class, 'show']);
        Route::get('/{slug}/statistics', [CampaignController::class, 'statistics']);
    });

    // Public Twibbon Generation (guests can generate)
    Route::post('/twibbons/generate', [UserGeneratedImageController::class, 'generate']);
    Route::get('/twibbons/{id}', [UserGeneratedImageController::class, 'show']);
    Route::get('/twibbons/{id}/download', [UserGeneratedImageController::class, 'download']);
    Route::post('/twibbons/{id}/share', [UserGeneratedImageController::class, 'share']);

    // ============================================
    // PROTECTED ROUTES (Authentication Required)
    // ============================================

    Route::middleware('auth:sanctum')->group(function () {

        // --------------------------------------------
        // Authentication & Profile
        // --------------------------------------------
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutAll']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::put('/profile', [AuthController::class, 'updateProfile']);
            Route::post('/change-password', [AuthController::class, 'changePassword']);
            Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
            Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
        });

        // --------------------------------------------
        // Dashboard & Statistics
        // --------------------------------------------
        Route::get('/dashboard', [StatisticsController::class, 'userDashboard']);
        Route::get('/statistics/global', [StatisticsController::class, 'global'])->middleware('role:Admin');

        // --------------------------------------------
        // Campaigns Management
        // --------------------------------------------
        Route::prefix('campaigns')->group(function () {
            Route::get('/my-campaigns', [CampaignController::class, 'myCampaigns']);
            Route::post('/', [CampaignController::class, 'store']);
            Route::put('/{id}', [CampaignController::class, 'update']);
            Route::delete('/{id}', [CampaignController::class, 'destroy']);
            Route::post('/{id}/publish', [CampaignController::class, 'publish']);
            Route::post('/{id}/unpublish', [CampaignController::class, 'unpublish']);
            Route::post('/{id}/duplicate', [CampaignController::class, 'duplicate']);

            // Campaign Statistics (protected)
            Route::get('/{id}/statistics/daily', [StatisticsController::class, 'campaignDaily']);
            Route::get('/{id}/statistics/export', [StatisticsController::class, 'exportCsv']);
            Route::get('/{id}/statistics/realtime', [StatisticsController::class, 'realtime']);
        });

        // --------------------------------------------
        // Campaign Templates
        // --------------------------------------------
        Route::prefix('templates')->group(function () {
            Route::post('/', [CampaignTemplateController::class, 'store']);
            Route::put('/{id}', [CampaignTemplateController::class, 'update']);
            Route::delete('/{id}', [CampaignTemplateController::class, 'destroy']);
            Route::post('/{id}/set-primary', [CampaignTemplateController::class, 'setPrimary']);
            Route::post('/{id}/duplicate', [CampaignTemplateController::class, 'duplicate']);
        });

        // --------------------------------------------
        // AI Generation
        // --------------------------------------------
        Route::prefix('ai-generations')->group(function () {
            Route::get('/', [AIGenerationController::class, 'index']);
            Route::post('/generate', [AIGenerationController::class, 'generate']);
            Route::get('/stats', [AIGenerationController::class, 'stats']);
            Route::get('/{id}', [AIGenerationController::class, 'status']);
            Route::post('/{id}/retry', [AIGenerationController::class, 'retry']);
            Route::post('/{id}/select', [AIGenerationController::class, 'selectResult']);
            Route::delete('/{id}', [AIGenerationController::class, 'destroy']);
        });

        // --------------------------------------------
        // Brand Kits
        // --------------------------------------------
        Route::prefix('brand-kits')->group(function () {
            Route::get('/', [BrandKitController::class, 'index']);
            Route::post('/', [BrandKitController::class, 'store']);
            Route::get('/{id}', [BrandKitController::class, 'show']);
            Route::put('/{id}', [BrandKitController::class, 'update']);
            Route::delete('/{id}', [BrandKitController::class, 'destroy']);
            Route::post('/{id}/set-default', [BrandKitController::class, 'setDefault']);
            Route::post('/{id}/apply-to-campaign', [BrandKitController::class, 'applyToCampaign']);
            Route::post('/{id}/duplicate', [BrandKitController::class, 'duplicate']);
            Route::post('/generate-palette', [BrandKitController::class, 'generatePalette']);
        });

        // --------------------------------------------
        // Video Jobs
        // --------------------------------------------
        Route::prefix('video-jobs')->group(function () {
            Route::get('/templates', [VideoJobController::class, 'templates']);
            Route::get('/', [VideoJobController::class, 'index']);
            Route::get('/pending', [VideoJobController::class, 'pending']);
            Route::get('/completed', [VideoJobController::class, 'completed']);
            Route::get('/stats', [VideoJobController::class, 'stats']);
            Route::post('/', [VideoJobController::class, 'store']);
            Route::get('/{id}', [VideoJobController::class, 'status']);
            Route::get('/{id}/download', [VideoJobController::class, 'download']);
            Route::post('/{id}/retry', [VideoJobController::class, 'retry']);
            Route::post('/{id}/cancel', [VideoJobController::class, 'cancel']);
            Route::delete('/{id}', [VideoJobController::class, 'destroy']);
        });

        // --------------------------------------------
        // User Generated Images (Twibbons)
        // --------------------------------------------
        Route::prefix('twibbons')->group(function () {
            Route::get('/my-twibbons', [UserGeneratedImageController::class, 'myTwibbons']);
            Route::delete('/{id}', [UserGeneratedImageController::class, 'destroy']);
        });

    });

});

// ============================================
// RATE LIMITING CONFIGURATION
// ============================================

Route::middleware('throttle:api')->group(function () {
    // All API routes inherit this rate limit
});

// Specific rate limits for resource-intensive endpoints
Route::middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
    Route::post('/v1/ai-generations/generate', [AIGenerationController::class, 'generate']);
    Route::post('/v1/video-jobs', [VideoJobController::class, 'store']);
});

Route::middleware('throttle:30,1')->group(function () {
    Route::post('/v1/twibbons/generate', [UserGeneratedImageController::class, 'generate']);
});

// ============================================
// HEALTH CHECK & STATUS
// ============================================

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'service' => 'Twibbons API',
        'version' => 'v1.0.0',
    ]);
});

Route::get('/status', function () {
    return response()->json([
        'api' => 'online',
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'redis' => Redis::connection()->ping() ? 'connected' : 'disconnected',
        'storage' => Storage::disk('s3')->exists('test.txt') ? 'connected' : 'disconnected',
    ]);
})->middleware('auth:sanctum');

// ============================================
// FALLBACK ROUTE
// ============================================

Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint not found',
        'available_versions' => ['v1'],
    ], 404);
});
