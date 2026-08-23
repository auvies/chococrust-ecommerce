<?php

use App\Http\Controllers\Api\V1\Admin\AccessCheckController;
use App\Http\Controllers\Api\V1\Ai\ToolCheckController;
use Illuminate\Support\Facades\Route;

/*
| Phase 03 boundary-test scaffolding, kept alongside the real modules
| added in Phase 04 - Tests\Feature\Auth\RbacBoundaryTest exercises these
| exact routes and is still the most direct proof the auth pipeline
| itself (not any one module's permission choices) is correctly enforced.
*/

Route::prefix('admin')->middleware(['auth:api', 'human'])->group(function (): void {
    Route::get('/dashboard', [AccessCheckController::class, 'dashboard'])
        ->middleware('role:super_admin,manager,order_manager,content_manager,support');
    Route::get('/settings', [AccessCheckController::class, 'settings'])
        ->middleware('permission:settings.manage');
    Route::get('/audit-logs', [AccessCheckController::class, 'auditLogs'])
        ->middleware('permission:audit.view');
    Route::get('/staff', [AccessCheckController::class, 'staff'])
        ->middleware('permission:staff.manage');
});

Route::prefix('ai')->middleware(['auth:api', 'permission:ai.tools.use'])->group(function (): void {
    Route::get('/ping', [ToolCheckController::class, 'ping']);
});
