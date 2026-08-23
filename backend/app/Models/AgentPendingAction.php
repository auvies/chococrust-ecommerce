<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agent_user_id', 'tool_name', 'agent_type', 'input', 'risk_reason', 'status',
    'reviewed_by', 'reviewed_at', 'rejection_reason', 'result', 'error',
])]
class AgentPendingAction extends Model
{
    protected function casts(): array
    {
        return [
            'input' => 'array',
            'result' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
