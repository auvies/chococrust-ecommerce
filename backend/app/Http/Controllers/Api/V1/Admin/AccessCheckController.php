<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Thin, non-business endpoints that exist only to exercise the auth/RBAC
 * pipeline end to end (Phase 03 is security foundation only - no admin
 * business modules exist yet). Each method's route middleware in
 * routes/api.php is the actual authorization boundary being tested; the
 * response body is deliberately trivial.
 */
class AccessCheckController extends Controller
{
    public function dashboard(): JsonResponse
    {
        return response()->json(['ok' => true, 'scope' => 'dashboard']);
    }

    public function settings(): JsonResponse
    {
        return response()->json(['ok' => true, 'scope' => 'settings']);
    }

    public function auditLogs(): JsonResponse
    {
        return response()->json(['ok' => true, 'scope' => 'audit-logs']);
    }

    public function staff(): JsonResponse
    {
        return response()->json(['ok' => true, 'scope' => 'staff']);
    }
}
