<?php

namespace Tests\Feature\Social;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class SocialAccountTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_status_requires_settings_manage_not_just_content_manage(): void
    {
        // content_manager is exactly the role that should NOT reach this -
        // integrations/secrets management is settings.manage/super_admin
        // territory per CLAUDE.md §6, not the broader content-editing role.
        $contentManager = $this->makeUserWithRole('content_manager');

        $this->actingAs($contentManager, 'api')->getJson('/api/v1/social/accounts')->assertStatus(403);
    }

    public function test_super_admin_can_read_connection_status(): void
    {
        $superAdmin = $this->makeUserWithRole('super_admin');

        $response = $this->actingAs($superAdmin, 'api')->getJson('/api/v1/social/accounts');

        $response->assertOk();
        $platforms = collect($response->json('data'))->pluck('platform');
        $this->assertTrue($platforms->contains('facebook'));
        $this->assertTrue($platforms->contains('instagram'));
    }

    public function test_disconnected_by_default_since_no_credentials_are_configured(): void
    {
        $superAdmin = $this->makeUserWithRole('super_admin');

        $response = $this->actingAs($superAdmin, 'api')->getJson('/api/v1/social/accounts');

        $response->assertJsonPath('data.0.connected', false);
        $response->assertJsonPath('data.1.connected', false);
    }

    public function test_the_response_never_contains_a_configured_secret_even_when_credentials_exist(): void
    {
        config([
            'services.facebook.enabled' => true,
            'services.facebook.app_id' => '1234567890',
            'services.facebook.app_secret' => 'super-secret-fb-value',
            'services.facebook.page_access_token' => 'super-secret-page-token',
            'services.instagram.enabled' => true,
            'services.instagram.business_account_id' => 'ig-biz-1',
            'services.instagram.access_token' => 'super-secret-ig-token',
        ]);
        $superAdmin = $this->makeUserWithRole('super_admin');

        $response = $this->actingAs($superAdmin, 'api')->getJson('/api/v1/social/accounts');

        $response->assertOk();
        $response->assertJsonPath('data.0.connected', true);
        $response->assertJsonPath('data.1.connected', true);

        $body = $response->getContent();
        $this->assertStringNotContainsString('super-secret-fb-value', $body);
        $this->assertStringNotContainsString('super-secret-page-token', $body);
        $this->assertStringNotContainsString('super-secret-ig-token', $body);
        // Non-secret identifiers are fine to show.
        $this->assertStringContainsString('ig-biz-1', $body);
    }

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/social/accounts')->assertStatus(401);
    }

    public function test_an_ai_agent_cannot_reach_integration_status(): void
    {
        $agent = $this->makeAiAgent();

        $this->actingAs($agent, 'api')->getJson('/api/v1/social/accounts')->assertStatus(403);
    }
}
