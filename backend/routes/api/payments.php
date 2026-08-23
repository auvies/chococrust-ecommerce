<?php

use App\Http\Controllers\Api\V1\Payments\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api'])->group(function (): void {
    Route::get('/payments', [PaymentController::class, 'index'])->middleware('human');
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);

    // CLAUDE.md §13: idempotent, payment-affecting.
    Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])
        ->middleware(['human', 'idempotent', 'csrf.cookie']);

    Route::get('/cod-records', [PaymentController::class, 'indexCod'])->middleware('human');
    Route::get('/cod-records/{codRecord}', [PaymentController::class, 'showCod'])->middleware('human');

    // CLAUDE.md §13: idempotent - every one of these is payment-sensitive
    // (moves cash-collection or payment state) and a retried request must
    // replay, never double-process.
    Route::patch('/cod-records/{codRecord}/collect', [PaymentController::class, 'collectCod'])
        ->middleware(['human', 'idempotent', 'csrf.cookie']);
    Route::patch('/cod-records/{codRecord}/verify', [PaymentController::class, 'verifyCod'])
        ->middleware(['human', 'idempotent', 'csrf.cookie']);
    Route::patch('/cod-records/{codRecord}/fail', [PaymentController::class, 'failCod'])
        ->middleware(['human', 'idempotent', 'csrf.cookie']);
    Route::patch('/cod-records/{codRecord}/return', [PaymentController::class, 'returnCod'])
        ->middleware(['human', 'idempotent', 'csrf.cookie']);
});
