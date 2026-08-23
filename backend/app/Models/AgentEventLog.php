<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event_type', 'subject_type', 'subject_id', 'payload'])]
class AgentEventLog extends Model
{
    /**
     * Append-only - no updated_at, matching InventoryMovement/
     * DeliveryTrackingEvent. Unlike those two, this table's timestamp
     * column is `occurred_at` (not `created_at` - see the migration), so
     * CREATED_AT must be told that explicitly or Eloquent's automatic
     * timestamping tries to insert a `created_at` column that doesn't
     * exist. Found by actually running the test suite for the first
     * time this phase - every event-dispatching test (order creation,
     * COD verification, agent approval) was failing with a 500 before
     * this fix.
     */
    const CREATED_AT = 'occurred_at';

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
