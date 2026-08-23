<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Demonstrates the narrow, allow-listed surface an AI agent identity is
 * meant to reach (CLAUDE.md §9/§23) - the ai.tools.use permission and
 * nothing else. Real tool-call endpoints are future business work; this
 * exists only to prove ai_agent accounts can reach their own lane while
 * the "human" middleware keeps them out of /api/v1/admin/* (see
 * RbacBoundaryTest).
 */
class ToolCheckController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json(['ok' => true, 'scope' => 'ai-tools']);
    }
}
