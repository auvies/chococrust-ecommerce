<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base for the 9 named lifecycle events (ORDER_CREATED, PAYMENT_SUBMITTED,
 * PAYMENT_VERIFIED, ORDER_CONFIRMED, ORDER_READY, ORDER_DISPATCHED,
 * ORDER_DELIVERED, ORDER_CANCELLED, REFUND_COMPLETED). Every concrete
 * event carries only compact scalar identifiers, never a full Eloquent
 * model - "optimize for low AI token usage" applies here exactly as it did
 * to Phase 12's chatbot context-bounding: a future agent listening for
 * "what happened" should never receive (or pay to process) a serialized
 * model when a handful of ids and a status string say the same thing.
 *
 * `LogAgentEvent` (the one listener every one of these is wired to, see
 * AppServiceProvider::boot()) writes `payload()` straight into
 * `agent_event_logs` - the durable feed a future agent's "get recent
 * events" tool reads from instead of ever querying orders/payments
 * directly.
 */
abstract class DomainEvent
{
    use Dispatchable;

    /** One of the 9 fixed event-type strings, e.g. 'ORDER_CREATED'. */
    abstract public function eventType(): string;

    /** @return array{type: ?string, id: ?int} what this event is about, for agent_event_logs' subject_type/subject_id columns. */
    abstract public function subject(): array;

    /** @return array<string, mixed> compact, bounded - never a model dump. */
    abstract public function payload(): array;
}
