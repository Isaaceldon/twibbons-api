<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandKitController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\VideoJobController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\AIGenerationController;
// routes/api.php
Route::prefix('v1')->group(function () {
    // Public
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);

    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::get('/campaigns/{slug}', [CampaignController::class, 'show']);
    Route::get('/campaigns/popular', [CampaignController::class, 'popular']);

    // Protected
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::apiResource('campaigns', CampaignController::class)->except(['index', 'show']);
        Route::apiResource('ai-generations', AIGenerationController::class);
        Route::apiResource('brand-kits', BrandKitController::class);
        Route::apiResource('video-jobs', VideoJobController::class);

        Route::get('/dashboard', [StatisticsController::class, 'userDashboard']);
    });
});

