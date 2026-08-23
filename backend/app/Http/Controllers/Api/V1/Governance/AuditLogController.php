<?php

namespace App\Http\Controllers\Api\V1\Governance;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CLAUDE.md §15: only accessible to admin/super_admin (audit.view) for review - read-only, no update/delete route exists at all. */
class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['audit.view']), 403);

        $logs = ApiQuery::for($request, AuditLog::query())
            ->filters(['action', 'user_id', 'auditable_type'])
            ->sorts(['created_at'])
            ->paginate();

        return AuditLogResource::collection($logs)->response();
    }
}
