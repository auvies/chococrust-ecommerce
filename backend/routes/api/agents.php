<?php

use App\Http\Controllers\Api\V1\Agents\AgentPendingActionController;
use Illuminate\Support\Facades\Route;

// Staff-only, never reachable by an agent identity itself - see
// AgentPendingActionController's own docblock for the double-permission
// gate on approve/reject.
Route::middleware(['auth:api', 'human'])->group(function (): void {
    Route::get('/agent-actions', [AgentPendingActionController::class, 'index']);
    Route::get('/agent-actions/{pendingAction}', [AgentPendingActionController::class, 'show']);

    Route::middleware('csrf.cookie')->group(function (): void {
        Route::post('/agent-actions/{pendingAction}/approve', [AgentPendingActionController::class, 'approve']);
        Route::post('/agent-actions/{pendingAction}/reject', [AgentPendingActionController::class, 'reject']);
    });
});
