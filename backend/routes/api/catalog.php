<?php

use App\Http\Controllers\Api\V1\Catalog\CategoryController;
use App\Http\Controllers\Api\V1\Catalog\DeliveryRuleController;
use App\Http\Controllers\Api\V1\Catalog\ProductController;
use App\Http\Controllers\Api\V1\Catalog\ProductVariantController;
use Illuminate\Support\Facades\Route;

// Read is public (CLAUDE.md §3's own example of deliberately public data -
// a storefront needs to list categories/products without an account). A
// staff caller (products.view/manage) additionally sees draft/archived
// products - handled inside the controllers, not the route.
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('/products', [ProductController::class, 'index']);
// Static route registered before the {product} wildcard so "best-sellers"
// is never swallowed by Product route-model binding.
Route::get('/products/best-sellers', [ProductController::class, 'bestSellers']);
Route::get('/products/{product}', [ProductController::class, 'show']);

// A pre-existing gap found and fixed while adding the category image
// upload route below: every other mutating module in this API applies
// `csrf.cookie` per state-changing route (see delivery-rules just below,
// or payments/orders/customers elsewhere) - this group never did, for
// categories, products, or variants. CLAUDE.md §2/§16: fix insecure code
// immediately on discovery rather than leaving it for a later phase.
Route::middleware(['auth:api', 'human', 'csrf.cookie'])->group(function (): void {
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::post('/categories/{category}/image', [CategoryController::class, 'uploadImage']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);

    Route::post('/products/{product}/variants', [ProductVariantController::class, 'store']);
    Route::put('/products/{product}/variants/{variant}', [ProductVariantController::class, 'update']);
    Route::delete('/products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy']);
});

// Delivery rule config has no public read - staff-only end to end (Phase 06).
Route::middleware(['auth:api', 'human'])->group(function (): void {
    Route::get('/delivery-rules', [DeliveryRuleController::class, 'index']);
    Route::post('/delivery-rules', [DeliveryRuleController::class, 'store'])->middleware('csrf.cookie');
    Route::put('/delivery-rules/{deliveryRule}', [DeliveryRuleController::class, 'update'])->middleware('csrf.cookie');
    Route::delete('/delivery-rules/{deliveryRule}', [DeliveryRuleController::class, 'destroy'])->middleware('csrf.cookie');
});
