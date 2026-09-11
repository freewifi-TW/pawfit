<?php

use App\Http\Controllers\Admin\ActionController as AdminActionController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FursonaController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShareLinkController;
use Illuminate\Support\Facades\Route;

/*
 |------------------------------------------------------------------
 | 認證（FR-1.1）
 |------------------------------------------------------------------
 */
Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::get('google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('google/callback', [AuthController::class, 'handleGoogleCallback']);
    Route::post('dev-login', [AuthController::class, 'devLogin']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

/*
 |------------------------------------------------------------------
 | 本人（onboarding 前也能用）
 |------------------------------------------------------------------
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [MeController::class, 'show']);
    Route::patch('me', [MeController::class, 'update']);
    Route::get('me/pawfit-id-available', [MeController::class, 'pawfitIdAvailable']);
});

/*
 |------------------------------------------------------------------
 | 內容管理（需完成 onboarding、未停權）
 |------------------------------------------------------------------
 */
Route::middleware(['auth:sanctum', 'not-banned', 'onboarded'])->group(function () {
    Route::apiResource('fursonas', FursonaController::class);
    Route::post('fursonas/{fursona}/media/reorder', [FursonaController::class, 'reorderMedia']);
    Route::post('fursonas/{fursona}/share-link', [ShareLinkController::class, 'store']);

    Route::patch('share-links/{shareLink}', [ShareLinkController::class, 'update']);
    Route::delete('share-links/{shareLink}', [ShareLinkController::class, 'destroy']);

    Route::post('media/presign', [MediaController::class, 'presign'])->middleware('throttle:presign');
    Route::post('media/confirm', [MediaController::class, 'confirm']);
    Route::post('media/abandon', [MediaController::class, 'abandon']);
    Route::patch('media/{media}', [MediaController::class, 'update']);
    Route::delete('media/{media}', [MediaController::class, 'destroy']);

    Route::post('reports', [ReportController::class, 'store'])->middleware('throttle:reports');
});

/*
 |------------------------------------------------------------------
 | 公開讀取（訪客可用；登入者會依偏好套用 NSFW 矩陣）
 |------------------------------------------------------------------
 */
Route::middleware('throttle:public')->group(function () {
    Route::get('img/{media}', [ImageController::class, 'show']);
    Route::get('share/{slug}', [PublicController::class, 'share']);
    Route::get('users/{pawfitId}', [PublicController::class, 'profile']);
});

/*
 |------------------------------------------------------------------
 | 管理後台（環境變數名單）
 |------------------------------------------------------------------
 */
Route::prefix('admin')->middleware(['auth:sanctum', 'can:admin'])->group(function () {
    Route::get('reports', [AdminReportController::class, 'index']);
    Route::get('stats', [AdminReportController::class, 'stats']);
    Route::post('actions', [AdminActionController::class, 'store']);
});
