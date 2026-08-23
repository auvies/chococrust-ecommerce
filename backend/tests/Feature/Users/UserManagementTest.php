<?php

namespace Tests\Feature\Users;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    public function test_listing_staff_requires_staff_manage_permission(): void
    {
        $support = $this->makeUserWithRole('support');
        $this->actingAs($support, 'api')->getJson('/api/v1/users')->assertStatus(403);

        $manager = $this->makeUserWithRole('manager');
        $this->actingAs($manager, 'api')->getJson('/api/v1/users')->assertOk();
    }

    public function test_deactivating_a_user_immediately_invalidates_their_session(): void
    {
        $manager = $this->makeUserWithRole('manager');
        $target = $this->makeUserWithRole('support');
        $originalVersion = $target->token_version;

        $response = $this->actingAs($manager, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->patchJson("/api/v1/users/{$target->id}/deactivate");
        $response->assertOk();
        $response->assertJsonPath('data.is_active', false);

        $target->refresh();
        $this->assertFalse($target->is_active);
        $this->assertGreaterThan($originalVersion, $target->token_version);
    }

    public function test_the_role_assignment_capability_does_not_exist_over_http(): void
    {
        // Deliberate: role assignment is CLI-only (php artisan role:assign),
        // never an HTTP endpoint, even for super_admin - see ADR 0003.
        $superAdmin = $this->makeUserWithRole('super_admin');
        $target = $this->makeUserWithRole('customer');

        $this->actingAs($superAdmin, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->postJson("/api/v1/users/{$target->id}/roles", ['role' => 'super_admin'])
            ->assertStatus(404);
    }

    public function test_roles_listing_shows_permissions(): void
    {
        $manager = $this->makeUserWithRole('manager');

        $response = $this->actingAs($manager, 'api')->getJson('/api/v1/roles');

        $response->assertOk();
        $response->assertJsonFragment(['slug' => 'ai_agent']);
    }
}
