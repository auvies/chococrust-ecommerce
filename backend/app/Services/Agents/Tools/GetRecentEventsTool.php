<?php

namespace App\Services\Agents\Tools;

use App\Models\AgentEventLog;
use App\Models\User;
use App\Services\Agents\AgentType;
use App\Services\Agents\Contracts\AgentToolInterface;

/**
 * Read-only, low-risk. The event architecture's actual consumption point:
 * an agent asking "what's happened lately" reads the compact
 * `agent_event_logs` feed (ORDER_CREATED, PAYMENT_VERIFIED, ...) instead of
 * ever querying orders/payments/deliveries directly - the concrete
 * mechanism behind "agents must NOT directly access the database."
 * Bounded to at most 20 rows and a truncated payload per row, matching
 * Phase 12's "never send unbounded data" cost principle.
 */
class GetRecentEventsTool implements AgentToolInterface
{
    private const MAX_RESULTS = 20;

    public function name(): string
    {
        return 'get_recent_events';
    }

    public function allowedAgentTypes(): array
    {
        return [AgentType::ORDER, AgentType::DISPATCH, AgentType::ANALYTICS];
    }

    public function isHighRisk(): bool
    {
        return false;
    }

    public function requiredApprovalPermission(): string
    {
        return ''; // never queued for approval - isHighRisk() is false
    }

    public function rules(): array
    {
        return [
            'event_type' => ['sometimes', 'nullable', 'string', 'max:64'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_RESULTS],
        ];
    }

    public function handle(array $input, User $actor): array
    {
        $query = AgentEventLog::query()->latest('occurred_at');

        if (! empty($input['event_type'])) {
            $query->where('event_type', $input['event_type']);
        }

        $limit = min((int) ($input['limit'] ?? self::MAX_RESULTS), self::MAX_RESULTS);

        $events = $query->limit($limit)->get(['event_type', 'subject_type', 'subject_id', 'payload', 'occurred_at']);

        return ['events' => $events->toArray()];
    }
}
