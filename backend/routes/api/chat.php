<?php

use App\Http\Controllers\Api\V1\Chat\ChatController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api'])->group(function (): void {
    Route::get('/chat/conversations', [ChatController::class, 'index']);
    Route::get('/chat/conversations/{conversation}', [ChatController::class, 'show']);

    Route::middleware('csrf.cookie')->group(function (): void {
        Route::post('/chat/conversations', [ChatController::class, 'store']);
        Route::post('/chat/conversations/{conversation}/messages', [ChatController::class, 'sendMessage'])->middleware('throttle:chat');
        Route::post('/chat/conversations/{conversation}/close', [ChatController::class, 'close']);
    });
});
