<?php

use App\Http\Controllers\Api\V1\Orders\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api'])->group(function (): void {
    Route::get('/orders', [OrderController::class, 'index']);
    // Static route registered before the {order} wildcard, same reasoning
    // as /products/best-sellers (Phase 07): never let it be swallowed by
    // Order route-model binding.
    Route::post('/orders/delivery-eligibility', [OrderController::class, 'checkDeliveryEligibility'])
        ->middleware('csrf.cookie');
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    // CLAUDE.md §13: idempotent - a retried checkout replays the first
    // order instead of placing a duplicate one.
    Route::post('/orders', [OrderController::class, 'store'])->middleware(['idempotent', 'csrf.cookie']);

    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware('csrf.cookie');
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])
        ->middleware(['human', 'csrf.cookie']);
});
