<?php

namespace App\Listeners;

use App\Events\DomainEvent;
use App\Models\AgentEventLog;

/**
 * The event architecture's one and only subscriber (registered for all 9
 * event classes in AppServiceProvider::boot()) - writes a compact,
 * append-only row to `agent_event_logs`, the feed GetRecentEventsTool
 * reads from. Deliberately the only thing that happens on these events
 * right now: no agent actually reacts to them yet ("Do not build
 * unnecessary AI agents now") - this just makes sure the feed exists and
 * is complete, so a future agent has something real to subscribe to.
 */
class LogAgentEvent
{
    public function handle(DomainEvent $event): void
    {
        $subject = $event->subject();

        AgentEventLog::create([
            'event_type' => $event->eventType(),
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
            'payload' => $event->payload(),
        ]);
    }
}
