<?php

use App\Http\Controllers\Api\V1\Social\SocialAccountController;
use Illuminate\Support\Facades\Route;

// Staff-only status check, never public - see SocialAccountController's
// own docblock for why this is "prepared, not built."
Route::middleware(['auth:api', 'human'])->group(function (): void {
    Route::get('/social/accounts', [SocialAccountController::class, 'status']);
});
