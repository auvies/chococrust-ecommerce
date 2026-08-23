<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * The only way to grant a staff role (including the first super_admin) -
 * deliberately CLI-only, never an HTTP endpoint that could be reached
 * remotely, and never seeded with a hardcoded account/password
 * (CLAUDE.md §6). Running this requires server/deploy access already, which
 * is the actual trust boundary.
 *
 * Usage: php artisan role:assign user@example.com super_admin
 */
class AssignRole extends Command
{
    protected $signature = 'role:assign {email} {role}';

    protected $description = 'Assign a role to an existing user account (CLI-only privilege escalation).';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user found with that email. The account must already exist (register it first).');

            return self::FAILURE;
        }

        $role = Role::where('slug', $this->argument('role'))->first();

        if (! $role) {
            $this->error('Unknown role slug. Known roles: '.Role::pluck('slug')->implode(', '));

            return self::FAILURE;
        }

        // Structural guard, mirroring EnsureUserIsHuman: an ai_agent
        // identity can only ever hold the ai_agent role, and a human
        // account can never hold it.
        if ($role->slug === 'ai_agent' && ! $user->isAiAgent()) {
            $this->error("Refusing to assign 'ai_agent' to a human-type account.");

            return self::FAILURE;
        }

        if ($role->slug !== 'ai_agent' && $user->isAiAgent()) {
            $this->error('Refusing to assign a staff role to an ai_agent-type account.');

            return self::FAILURE;
        }

        $user->roles()->syncWithoutDetaching([$role->id]);

        AuditLog::create([
            'user_id' => null,
            'action' => 'auth.role_assigned_via_cli',
            'auditable_type' => 'user',
            'auditable_id' => $user->id,
            'after' => ['role' => $role->slug],
        ]);

        $this->info("Assigned role '{$role->slug}' to {$user->email}.");

        return self::SUCCESS;
    }
}
