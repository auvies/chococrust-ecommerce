<?php

use App\Http\Controllers\Api\V1\Inventory\InventoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'human'])->group(function (): void {
    Route::get('/inventory', [InventoryController::class, 'index']);
    // csrf.cookie added (SECURITY_AUDIT.md): this was the only staff
    // mutation module in the API with no CSRF protection at all, unlike
    // every comparable module (catalog, delivery, orders, payments, ...).
    Route::post('/inventory', [InventoryController::class, 'store'])->middleware('csrf.cookie');
    Route::patch('/inventory/{inventory}/adjust', [InventoryController::class, 'adjust'])->middleware('csrf.cookie');
    Route::get('/inventory/{inventory}/history', [InventoryController::class, 'history']);
});
