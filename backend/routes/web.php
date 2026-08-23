<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This backend is a REST API only (no server-rendered frontend - see
| CLAUDE.md §1). This route exists purely so the bare domain resolves
| to something other than a 404; all real traffic goes through /api/v1/*
| or the /up health check.
|
*/

Route::get('/', function () {
    return response()->json([
        'service' => 'choco-crust-backend',
        'status' => 'ok',
    ]);
});
