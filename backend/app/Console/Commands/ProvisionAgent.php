<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Agents\AgentType;
use Illuminate\Console\Command;

/**
 * Provisions an existing ai_agent-type account as one of the 8 named
 * future agents (Sales/Support/Order/Payment/Inventory/Marketing/Dispatch/
 * Analytics), scoping which tools it may call
 * (AgentToolInterface::allowedAgentTypes()). Deliberately CLI-only, same
 * trust boundary as `role:assign` (App\Console\Commands\AssignRole) - no
 * HTTP endpoint could ever create or reclassify an agent identity.
 *
 * This command existing does not mean any agent has actually been
 * deployed - "Do not build unnecessary AI agents now" (Phase 13's own
 * brief). It's the provisioning mechanism for when one is.
 *
 * Usage: php artisan agent:provision agent@example.com inventory_agent
 */
class ProvisionAgent extends Command
{
    protected $signature = 'agent:provision {email} {agent_type}';

    protected $description = 'Provision an existing ai_agent-type account as a named future agent (CLI-only).';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user found with that email. The account must already exist.');

            return self::FAILURE;
        }

        if (! $user->isAiAgent()) {
            $this->error("Refusing to set agent_type on a non-ai_agent-type account (this account's type is '{$user->type}').");

            return self::FAILURE;
        }

        $agentType = $this->argument('agent_type');

        if (! in_array($agentType, AgentType::ALL, true)) {
            $this->error('Unknown agent type. Known types: '.implode(', ', AgentType::ALL));

            return self::FAILURE;
        }

        $role = Role::where('slug', 'ai_agent')->first();
        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        $before = ['agent_type' => $user->agent_type];
        $user->update(['agent_type' => $agentType]);

        AuditLog::create([
            'user_id' => null,
            'action' => 'agent.provisioned',
            'auditable_type' => 'user',
            'auditable_id' => $user->id,
            'before' => $before,
            'after' => ['agent_type' => $agentType],
        ]);

        $this->info("Provisioned {$user->email} as '{$agentType}'.");

        return self::SUCCESS;
    }
}
