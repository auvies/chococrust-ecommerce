<?php

use App\Http\Controllers\Api\V1\Users\RoleController;
use App\Http\Controllers\Api\V1\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'human'])->group(function (): void {
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::patch('/users/{user}/activate', [UserController::class, 'activate'])->middleware('csrf.cookie');
    Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])->middleware('csrf.cookie');

    Route::get('/roles', [RoleController::class, 'index']);
});
