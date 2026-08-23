<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'choco-crust-backend',
        'time' => now()->toIso8601String(),
    ]);
})->name('api.v1.health');

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware(['throttle:token-refresh', 'csrf.cookie']);

    Route::middleware('auth:api')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('csrf.cookie');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->middleware('csrf.cookie');
        Route::get('/me', [AuthController::class, 'me']);
    });
});
