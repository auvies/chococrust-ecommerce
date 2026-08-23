<?php

use App\Http\Controllers\Api\V1\Reviews\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/products/{product}/reviews', [ReviewController::class, 'index']);

Route::middleware(['auth:api', 'human'])->get('/reviews', [ReviewController::class, 'all']);

Route::middleware(['auth:api'])->group(function (): void {
    Route::post('/products/{product}/reviews', [ReviewController::class, 'store'])->middleware('csrf.cookie');
    Route::patch('/reviews/{review}/approve', [ReviewController::class, 'approve'])->middleware(['human', 'csrf.cookie']);
    Route::delete('/reviews/{review}/reject', [ReviewController::class, 'reject'])->middleware(['human', 'csrf.cookie']);
});
