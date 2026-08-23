<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

trait InteractsWithRoles
{
    protected function seedRoles(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function makeUserWithRole(string $roleSlug, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $user->roles()->attach($role);

        return $user->fresh();
    }

    protected function makeAiAgent(array $attributes = []): User
    {
        $user = $this->makeUserWithRole('ai_agent', array_merge(['type' => 'ai_agent'], $attributes));

        return $user;
    }
}
