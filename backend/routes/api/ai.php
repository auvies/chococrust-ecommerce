<?php

use App\Http\Controllers\Api\V1\Ai\AiToolController;
use Illuminate\Support\Facades\Route;

// CLAUDE.md §9/§23: the ai_agent identity's own narrow lane - deliberately
// outside the /admin prefix and its "human" gate (see admin_demo.php for
// the /ai/ping boundary-test route this sits alongside).
Route::middleware(['auth:api', 'permission:ai.tools.use'])->group(function (): void {
    Route::post('/ai/tools/invoke', [AiToolController::class, 'invoke'])->middleware('throttle:ai-tools');
});
