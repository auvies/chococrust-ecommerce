<?php

use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\V1\Notifications\NotificationTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api'])->group(function (): void {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->middleware('csrf.cookie');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->middleware('csrf.cookie');

    // notifications.manage - staff who configure order/payment/delivery notification copy.
    Route::middleware('human')->group(function (): void {
        Route::get('/notification-templates', [NotificationTemplateController::class, 'index']);
        Route::patch('/notification-templates/{notificationTemplate}', [NotificationTemplateController::class, 'update'])->middleware('csrf.cookie');
    });
});
