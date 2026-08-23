<?php

use App\Http\Controllers\Api\V1\Customers\AddressController;
use App\Http\Controllers\Api\V1\Customers\CustomerController;
use App\Http\Controllers\Api\V1\Customers\CustomerNoteController;
use App\Http\Controllers\Api\V1\Customers\CustomerTagController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api'])->group(function (): void {
    // Self-service: any authenticated customer manages their own address book.
    // csrf.cookie added (SECURITY_AUDIT.md): these mutate a delivery
    // destination and were the only state-changing routes in this file
    // without it, unlike every sibling route below - a real gap given
    // production ships SameSite=None on the auth cookies (see
    // Authentication & Security in README.md).
    Route::get('/me/addresses', [AddressController::class, 'index']);
    Route::post('/me/addresses', [AddressController::class, 'store'])->middleware('csrf.cookie');
    Route::put('/me/addresses/{address}', [AddressController::class, 'update'])->middleware('csrf.cookie');
    Route::delete('/me/addresses/{address}', [AddressController::class, 'destroy'])->middleware('csrf.cookie');

    // Staff/self: object-level authorization decides access (see CustomerPolicy).
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('human');
    Route::get('/customers/{customer}', [CustomerController::class, 'show']);
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('csrf.cookie');

    // Staff-only, never self-service (see CustomerNoteController's own docblock).
    Route::middleware('human')->group(function (): void {
        Route::get('/customers/{customer}/notes', [CustomerNoteController::class, 'index']);
        Route::post('/customers/{customer}/notes', [CustomerNoteController::class, 'store'])->middleware('csrf.cookie');

        Route::get('/customer-tags', [CustomerTagController::class, 'index']);
        Route::post('/customer-tags', [CustomerTagController::class, 'store'])->middleware('csrf.cookie');
        Route::delete('/customer-tags/{tag}', [CustomerTagController::class, 'destroy'])->middleware('csrf.cookie');
        Route::post('/customers/{customer}/tags/{tag}', [CustomerTagController::class, 'attach'])->middleware('csrf.cookie');
        Route::delete('/customers/{customer}/tags/{tag}', [CustomerTagController::class, 'detach'])->middleware('csrf.cookie');
    });
});
