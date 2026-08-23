<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

/**
 * The core "test authorization boundaries" / "unauthorized users cannot
 * access admin APIs" requirement for Phase 03. Exercises every admin/AI
 * demo route (see AccessCheckController / ToolCheckController) against
 * every role to prove the middleware pipeline - not just individual
 * pieces of it - actually enforces least privilege end to end.
 */
class RbacBoundaryTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public static function adminRoutes(): array
    {
        return [
            'dashboard' => ['/api/v1/admin/dashboard'],
            'settings' => ['/api/v1/admin/settings'],
            'audit-logs' => ['/api/v1/admin/audit-logs'],
            'staff' => ['/api/v1/admin/staff'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_a_guest_cannot_reach_any_admin_route(string $route): void
    {
        $this->getJson($route)->assertStatus(401);
    }

    #[DataProvider('adminRoutes')]
    public function test_a_plain_customer_cannot_reach_any_admin_route(string $route): void
    {
        $customer = $this->makeUserWithRole('customer');

        $this->actingAs($customer, 'api')->getJson($route)->assertStatus(403);
    }

    /**
     * The structural block: even though ai_agent is a real role in the
     * roles table, it is never given any admin permission, AND the
     * EnsureUserIsHuman middleware rejects the account type outright -
     * belt and suspenders for "AI agents must NOT be normal administrators".
     */
    #[DataProvider('adminRoutes')]
    public function test_an_ai_agent_cannot_reach_any_admin_route(string $route): void
    {
        $agent = $this->makeAiAgent();

        $this->actingAs($agent, 'api')->getJson($route)->assertStatus(403);
    }

    public function test_super_admin_can_reach_every_admin_route(): void
    {
        $superAdmin = $this->makeUserWithRole('super_admin');
        $actor = $this->actingAs($superAdmin, 'api');

        foreach (self::adminRoutes() as [$route]) {
            $actor->getJson($route)->assertOk();
        }
    }

    public function test_manager_can_reach_operational_routes_but_not_settings(): void
    {
        $manager = $this->makeUserWithRole('manager');
        $actor = $this->actingAs($manager, 'api');

        $actor->getJson('/api/v1/admin/dashboard')->assertOk();
        $actor->getJson('/api/v1/admin/audit-logs')->assertOk();
        $actor->getJson('/api/v1/admin/staff')->assertOk();

        // settings.manage is reserved for super_admin per CLAUDE.md §8.
        $actor->getJson('/api/v1/admin/settings')->assertStatus(403);
    }

    public function test_order_manager_is_confined_to_the_dashboard(): void
    {
        $orderManager = $this->makeUserWithRole('order_manager');
        $actor = $this->actingAs($orderManager, 'api');

        $actor->getJson('/api/v1/admin/dashboard')->assertOk();
        $actor->getJson('/api/v1/admin/settings')->assertStatus(403);
        $actor->getJson('/api/v1/admin/audit-logs')->assertStatus(403);
        $actor->getJson('/api/v1/admin/staff')->assertStatus(403);
    }

    public function test_content_manager_is_confined_to_the_dashboard(): void
    {
        $contentManager = $this->makeUserWithRole('content_manager');
        $actor = $this->actingAs($contentManager, 'api');

        $actor->getJson('/api/v1/admin/dashboard')->assertOk();
        $actor->getJson('/api/v1/admin/settings')->assertStatus(403);
        $actor->getJson('/api/v1/admin/audit-logs')->assertStatus(403);
        $actor->getJson('/api/v1/admin/staff')->assertStatus(403);
    }

    public function test_support_is_confined_to_the_dashboard(): void
    {
        $support = $this->makeUserWithRole('support');
        $actor = $this->actingAs($support, 'api');

        $actor->getJson('/api/v1/admin/dashboard')->assertOk();
        $actor->getJson('/api/v1/admin/settings')->assertStatus(403);
        $actor->getJson('/api/v1/admin/audit-logs')->assertStatus(403);
        $actor->getJson('/api/v1/admin/staff')->assertStatus(403);
    }

    public function test_delivery_rider_cannot_reach_the_admin_dashboard_at_all(): void
    {
        // delivery_rider has no role/permission overlap with any admin
        // route - it's scoped entirely to its own deliveries (not built
        // yet, Phase 03 is auth/security foundation only).
        $rider = $this->makeUserWithRole('delivery_rider');
        $actor = $this->actingAs($rider, 'api');

        foreach (self::adminRoutes() as [$route]) {
            $actor->getJson($route)->assertStatus(403);
        }
    }

    public function test_ai_agent_role_can_reach_its_own_tool_route(): void
    {
        $agent = $this->makeAiAgent();
        $this->actingAs($agent, 'api')->getJson('/api/v1/ai/ping')->assertOk();
    }

    public function test_super_admin_cannot_reach_the_ai_tool_route(): void
    {
        // ai.tools.use is deliberately excluded from every human role, even
        // super_admin - it's the AI agent identity's own lane, not folded
        // into "has every permission" (RolePermissionSeeder::roles()).
        $superAdmin = $this->makeUserWithRole('super_admin');
        $this->actingAs($superAdmin, 'api')->getJson('/api/v1/ai/ping')->assertStatus(403);
    }

    public function test_customer_cannot_reach_the_ai_tool_route(): void
    {
        $customer = $this->makeUserWithRole('customer');
        $this->actingAs($customer, 'api')->getJson('/api/v1/ai/ping')->assertStatus(403);
    }

    public function test_a_guest_cannot_reach_the_ai_tool_route(): void
    {
        $this->getJson('/api/v1/ai/ping')->assertStatus(401);
    }

    public function test_a_deactivated_staff_account_loses_admin_access(): void
    {
        $manager = $this->makeUserWithRole('manager', ['is_active' => false]);

        // actingAs() bypasses cookie/guard resolution entirely, so this
        // proves the is_active check independently via a real token.
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $manager->email,
            'password' => 'password',
        ]);

        $login->assertStatus(403);
    }
}
