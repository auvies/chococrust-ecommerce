<?php

namespace Tests\Feature\Governance;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class GovernanceTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    public function test_audit_logs_are_only_visible_to_audit_view_permission_holders(): void
    {
        AuditLog::create(['action' => 'test.action']);
        $support = $this->makeUserWithRole('support');

        $this->actingAs($support, 'api')->getJson('/api/v1/audit-logs')->assertStatus(403);

        $superAdmin = $this->makeUserWithRole('super_admin');
        $this->actingAs($superAdmin, 'api')->getJson('/api/v1/audit-logs')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_public_settings_endpoint_hides_non_public_settings(): void
    {
        SystemSetting::factory()->create(['key' => 'store.name', 'value' => 'Choco Crust', 'is_public' => true]);
        SystemSetting::factory()->create(['key' => 'internal.flag', 'is_public' => false]);

        $response = $this->getJson('/api/v1/settings');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_only_super_admin_can_manage_settings(): void
    {
        $manager = $this->makeUserWithRole('manager');

        $this->actingAs($manager, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->putJson('/api/v1/settings', ['key' => 'x', 'value' => 'y', 'type' => 'string'])
            ->assertStatus(403);

        $superAdmin = $this->makeUserWithRole('super_admin');
        $this->actingAs($superAdmin, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->putJson('/api/v1/settings', ['key' => 'store.currency', 'value' => 'PKR', 'type' => 'string'])
            ->assertSuccessful();
    }

    public function test_analytics_overview_requires_analytics_view_permission(): void
    {
        $support = $this->makeUserWithRole('support');
        $this->actingAs($support, 'api')->getJson('/api/v1/analytics/overview')->assertStatus(403);

        $manager = $this->makeUserWithRole('manager');
        $this->actingAs($manager, 'api')->getJson('/api/v1/analytics/overview')->assertOk();
    }
}
