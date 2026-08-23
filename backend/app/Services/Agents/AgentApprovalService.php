<?php

namespace App\Services\Agents;

use App\Models\AgentPendingAction;
use App\Models\User;
use App\Services\Agents\Contracts\AgentToolInterface;
use App\Services\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * The human-approval half of the pipeline for high-risk tool calls
 * (CLAUDE.md §9/§23: "financially or operationally significant actions
 * require human approval"). `create()` is called by AgentToolGateway
 * instead of ever running a high-risk tool's handle() immediately;
 * `approve()`/`reject()` are only ever called by a human reviewer through
 * AgentPendingActionController, never by an agent.
 */
class AgentApprovalService
{
    public function create(User $agent, AgentToolInterface $tool, array $input): AgentPendingAction
    {
        $action = AgentPendingAction::create([
            'agent_user_id' => $agent->id,
            'tool_name' => $tool->name(),
            'agent_type' => $agent->agent_type,
            'input' => $input,
            'risk_reason' => $tool->name(),
            'status' => 'pending',
        ]);

        AuditLogger::log('agent_action.requested', $action, after: ['tool' => $tool->name(), 'input' => $input]);

        return $action;
    }

    /**
     * Approving and executing are one atomic step, deliberately - an
     * "approved but not yet executed" state would just be a second window
     * for the underlying data to drift before anything actually runs, with
     * no benefit over doing both at once. `$reviewer` becomes `handle()`'s
     * `$actor` - the accountable human, not the requesting agent (see
     * AgentToolInterface::handle()'s own docblock).
     *
     * Deliberately NOT wrapped in its own outer transaction: `handle()`
     * delegates to a Service (PaymentService::refund(), OrderService::
     * cancel(), ...) that already manages its own transactional integrity.
     * Wrapping this method in another transaction would mean a caught
     * failure's own status='failed' write gets rolled back along with
     * whatever the tool attempted - silently reverting the very audit
     * record meant to survive the failure.
     */
    public function approve(AgentPendingAction $action, User $reviewer, AgentToolRegistry $registry): AgentPendingAction
    {
        $this->assertPending($action);

        $tool = $registry->find($action->tool_name);
        if (! $tool) {
            throw ValidationException::withMessages(['tool_name' => "Tool '{$action->tool_name}' is no longer registered."]);
        }

        try {
            $result = $tool->handle($action->input, $reviewer);
        } catch (\Throwable $e) {
            // The tool's own business logic refused the request (e.g. a
            // refund amount that now exceeds what's left refundable,
            // because state changed between request and review) - the
            // review itself is still recorded, just as a failure, never
            // silently retried or left ambiguously "pending" forever.
            $action->update([
                'status' => 'failed',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'error' => $e->getMessage(),
            ]);

            AuditLogger::log('agent_action.failed', $action, after: ['error' => $e->getMessage()]);

            throw $e;
        }

        $action->update([
            'status' => 'executed',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'result' => $result,
        ]);

        AuditLogger::log('agent_action.approved', $action, after: ['result' => $result]);

        return $action->fresh();
    }

    public function reject(AgentPendingAction $action, User $reviewer, string $reason): AgentPendingAction
    {
        $this->assertPending($action);

        $action->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        AuditLogger::log('agent_action.rejected', $action, after: ['reason' => $reason]);

        return $action->fresh();
    }

    private function assertPending(AgentPendingAction $action): void
    {
        if ($action->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'This action has already been reviewed.']);
        }
    }
}
