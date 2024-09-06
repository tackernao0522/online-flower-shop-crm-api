<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\UserController;

Route::prefix('v1')->group(function () {
    // 認証が不要なルート
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/register', [AuthController::class, 'register']);

    // パスワードリセット関連のルート
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');

    // 認証が必要なルート
    Route::middleware(['auth:api', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);

        Route::apiResource('users', UserController::class);
        Route::apiResource('customers', CustomerController::class);

        Route::get('/user/online-status', [UserController::class, 'getCurrentUserOnlineStatus']);
        Route::post('/user/online-status', [UserController::class, 'updateOnlineStatus']);
        Route::get('/users/{user}/online-status', [UserController::class, 'getOnlineStatus']);
    });
});
