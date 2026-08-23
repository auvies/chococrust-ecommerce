<?php

namespace App\Http\Controllers\Api\V1\Agents;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgentPendingActionResource;
use App\Models\AgentPendingAction;
use App\Services\Agents\AgentApprovalService;
use App\Services\Agents\AgentToolRegistry;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff-facing review queue for high-risk agent tool calls (CLAUDE.md §9/
 * §23's human-approval requirement, made concrete). `agent_actions.manage`
 * only shows an admin the queue exists at all - `approve()`/`reject()`
 * additionally require the specific permission the underlying tool names
 * (AgentToolInterface::requiredApprovalPermission()), so approving a
 * refund still needs `orders.refund`, exactly as it would to issue one by
 * hand. There is deliberately no way to approve/reject via this
 * controller without both.
 */
class AgentPendingActionController extends Controller
{
    public function __construct(
        private readonly AgentApprovalService $approvals,
        private readonly AgentToolRegistry $registry,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['agent_actions.manage']), 403);

        $actions = ApiQuery::for($request, AgentPendingAction::query())
            ->filters(['status', 'tool_name'])
            ->sorts(['created_at'])
            ->paginate();

        return AgentPendingActionResource::collection($actions)->response();
    }

    public function show(Request $request, AgentPendingAction $pendingAction): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['agent_actions.manage']), 403);

        return AgentPendingActionResource::make($pendingAction)->response();
    }

    public function approve(Request $request, AgentPendingAction $pendingAction): JsonResponse
    {
        $this->authorizeReview($request, $pendingAction);

        $updated = $this->approvals->approve($pendingAction, $request->user(), $this->registry);

        return AgentPendingActionResource::make($updated)->response();
    }

    public function reject(Request $request, AgentPendingAction $pendingAction): JsonResponse
    {
        $this->authorizeReview($request, $pendingAction);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $updated = $this->approvals->reject($pendingAction, $request->user(), $data['reason']);

        return AgentPendingActionResource::make($updated)->response();
    }

    private function authorizeReview(Request $request, AgentPendingAction $pendingAction): void
    {
        abort_unless($request->user()->hasAnyPermission(['agent_actions.manage']), 403);

        $tool = $this->registry->find($pendingAction->tool_name);
        abort_if(! $tool, 404, 'This action\'s tool is no longer registered.');

        abort_unless($request->user()->hasAnyPermission([$tool->requiredApprovalPermission()]), 403);
    }
}
