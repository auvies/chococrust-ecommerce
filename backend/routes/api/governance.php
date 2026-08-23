<?php

use App\Http\Controllers\Api\V1\Governance\AuditLogController;
use App\Http\Controllers\Api\V1\Governance\SystemSettingController;
use Illuminate\Support\Facades\Route;

Route::get('/settings', [SystemSettingController::class, 'index']);

Route::middleware(['auth:api', 'human'])->group(function (): void {
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::put('/settings', [SystemSettingController::class, 'upsert'])->middleware('csrf.cookie');
});
