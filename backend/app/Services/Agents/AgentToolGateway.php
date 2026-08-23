<?php

namespace App\Services\Agents;

use App\Models\AiUsageLog;
use App\Models\User;
use App\Services\Ai\AiUsageLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The full pipeline the business specified:
 *
 *   Agent -> Approved Tool -> Validation -> Authorization -> Business
 *   Logic -> Database
 *
 * This is the single entry point every agent identity's tool call goes
 * through - `AiToolController` (the customer chatbot's tool-calling
 * surface, Phase 12) and any future agent-facing endpoint both call
 * `invoke()`, never a tool's `handle()` directly. Every call is logged to
 * `ai_usage_logs` regardless of outcome (refused/pending/success), the
 * same audit trail CLAUDE.md §9 already required of the pre-Phase-13
 * tool surface.
 */
class AgentToolGateway
{
    public function __construct(
        private readonly AgentToolRegistry $registry,
        private readonly AgentApprovalService $approvals,
        private readonly AiUsageLimiter $limiter,
    ) {}

    public function invoke(string $toolName, array $input, User $agent): array
    {
        // CLAUDE.md §19/§23: a configurable usage limit, checked before
        // anything else - an over-budget agent is refused (and logged as
        // such) the same way an unknown tool or a schema failure is,
        // rather than only being cost-capped on the chatbot's side.
        if ($this->limiter->agentHourlyLimitExceeded($agent) || $this->limiter->globalLimitExceeded()) {
            $this->log($agent, $toolName, $input, null, 'refused');

            throw ValidationException::withMessages(['tool' => 'Agent tool-call usage limit exceeded for this period.']);
        }

        // Approved Tool: only a name in the fixed registry can ever run.
        $tool = $this->registry->find($toolName);
        if (! $tool) {
            $this->log($agent, $toolName, $input, null, 'refused');

            throw ValidationException::withMessages(['tool' => "Tool '{$toolName}' is not in the allow-list."]);
        }

        // Authorization: an empty allow-list means unrestricted (see
        // AgentToolInterface::allowedAgentTypes()); otherwise the calling
        // identity's provisioned agent_type must be explicitly listed.
        if ($tool->allowedAgentTypes() !== [] && ! in_array($agent->agent_type, $tool->allowedAgentTypes(), true)) {
            $this->log($agent, $toolName, $input, null, 'refused');

            throw ValidationException::withMessages(['tool' => "Tool '{$toolName}' is not available to this agent."]);
        }

        // Validation: strict schema, before anything resembling business
        // logic runs.
        try {
            validator($input, $tool->rules())->validate();
        } catch (ValidationException $e) {
            $this->log($agent, $toolName, $input, null, 'refused');

            throw $e;
        }

        // High-risk operations (refund, payment verification, large
        // discount, price changes, bulk deletion, order cancellation,
        // dispatch cancellation) never reach Business Logic from a tool
        // call directly - they're queued for a human instead.
        if ($tool->isHighRisk()) {
            $pending = $this->approvals->create($agent, $tool, $input);
            $this->log($agent, $toolName, $input, ['pending_action_id' => $pending->id], 'pending');

            return ['status' => 'pending_approval', 'pending_action_id' => $pending->id];
        }

        // Business Logic -> Database: only ever through the tool's own
        // handle(), which only ever calls an existing Service class - see
        // AgentToolInterface's own docblock for why that's what "agents
        // must not directly access the database" means in code.
        try {
            $output = $tool->handle($input, $agent);
        } catch (\Throwable $e) {
            // A validated, authorized call can still fail inside handle()
            // (e.g. a business-rule ValidationException, or a race where a
            // referenced record was deleted between validation and
            // execution) - logged as 'error' rather than left with no
            // audit trail at all, then re-thrown so the caller still sees
            // the real failure.
            $this->log($agent, $toolName, $input, null, 'error');

            throw $e;
        }

        $this->log($agent, $toolName, $input, $output, 'success');

        return $output;
    }

    private function log(User $agent, string $toolName, array $input, ?array $output, string $status): void
    {
        AiUsageLog::create([
            'user_id' => $agent->id,
            'provider' => 'internal',
            'model' => 'tool-router',
            'purpose' => 'agent_tool_call',
            'tool_name' => $toolName,
            'tool_input' => $input,
            'tool_output' => $output,
            // ai_usage_logs.status is a native DB enum (success/error/
            // refused) with no 'pending' value - a queued high-risk
            // request was itself a successful, well-formed tool
            // invocation (it passed validation and authorization), so it
            // logs as 'success' here; AgentPendingAction.status is the
            // record of what happens to it next (pending/executed/
            // rejected/failed).
            'status' => $status === 'pending' ? 'success' : $status,
        ]);
    }
}
