<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Deny-by-default (CLAUDE.md §3/§7): every route declares its auth and
| role/permission requirements explicitly in the file it lives in. Nothing
| is protected by convention or by omission - the only public routes are
| /health, /auth/register, /auth/login, and the read-only storefront
| listings (categories, products, homepage, reviews) that CLAUDE.md §3
| names as the standard example of deliberately public data.
|
| One file per module under routes/api/ (CLAUDE.md §13: versioning
| strategy decided up front) instead of one growing file - each module's
| routes, and the module boundary itself, stay easy to find.
|
*/

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    require __DIR__.'/api/health_and_auth.php';
    require __DIR__.'/api/admin_demo.php';
    require __DIR__.'/api/users.php';
    require __DIR__.'/api/catalog.php';
    require __DIR__.'/api/customers.php';
    require __DIR__.'/api/inventory.php';
    require __DIR__.'/api/orders.php';
    require __DIR__.'/api/payments.php';
    require __DIR__.'/api/delivery.php';
    require __DIR__.'/api/reviews.php';
    require __DIR__.'/api/media.php';
    require __DIR__.'/api/content.php';
    require __DIR__.'/api/notifications.php';
    require __DIR__.'/api/social.php';
    require __DIR__.'/api/chat.php';
    require __DIR__.'/api/ai.php';
    require __DIR__.'/api/agents.php';
    require __DIR__.'/api/analytics.php';
    require __DIR__.'/api/governance.php';
});
