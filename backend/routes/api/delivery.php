<?php

use App\Http\Controllers\Api\V1\Delivery\DeliveryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'human'])->group(function (): void {
    Route::get('/deliveries', [DeliveryController::class, 'index']);
    Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show']);
    Route::post('/orders/{order}/delivery', [DeliveryController::class, 'store'])->middleware('csrf.cookie');
    Route::patch('/deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus'])->middleware('csrf.cookie');
});
