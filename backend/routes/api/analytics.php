<?php

use App\Http\Controllers\Api\V1\Analytics\AnalyticsController;
use Illuminate\Support\Facades\Route;

// CLAUDE.md §8: permission gating centralized at the route-group level,
// not repeated per controller method (the pre-existing inline
// abort_unless($request->user()->hasAnyPermission(...)) in every method
// was replaced by this single middleware when the controller was split
// out into AnalyticsService this phase - identical externally: any
// non-analytics.view holder still gets a 403 on every route below).
Route::middleware(['auth:api', 'human', 'permission:analytics.view'])->prefix('analytics')->group(function (): void {
    Route::get('/overview', [AnalyticsController::class, 'overview']);
    Route::get('/sales', [AnalyticsController::class, 'sales']);
    Route::get('/orders', [AnalyticsController::class, 'orders']);
    Route::get('/products', [AnalyticsController::class, 'productPerformance']);
    Route::get('/best-sellers', [AnalyticsController::class, 'bestSellers']);
    Route::get('/customers', [AnalyticsController::class, 'customers']);
    Route::get('/cod', [AnalyticsController::class, 'cod']);
    Route::get('/deliveries', [AnalyticsController::class, 'deliveries']);
    Route::get('/inventory', [AnalyticsController::class, 'inventory']);
    Route::get('/payments', [AnalyticsController::class, 'payments']);
    Route::get('/ai-usage', [AnalyticsController::class, 'aiUsage']);
});
