<?php

namespace Tests\Feature\Analytics;

use App\Models\AiUsageLog;
use App\Services\Agents\AgentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class AiUsageAnalyticsTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_ai_usage_requires_analytics_view(): void
    {
        $support = $this->makeUserWithRole('support');

        $this->actingAs($support, 'api')->getJson('/api/v1/analytics/ai-usage')->assertStatus(403);
    }

    public function test_ai_usage_summarizes_cost_and_the_deterministic_vs_ai_split(): void
    {
        AiUsageLog::factory()->count(3)->create(['provider' => 'internal', 'cost_usd' => 0, 'status' => 'success']);
        AiUsageLog::factory()->count(2)->create(['provider' => 'anthropic', 'cost_usd' => 0.001234, 'status' => 'success']);
        AiUsageLog::factory()->create(['provider' => 'anthropic', 'status' => 'error']);
        AiUsageLog::factory()->create(['provider' => 'internal', 'status' => 'refused']);

        $manager = $this->makeUserWithRole('manager');

        $response = $this->actingAs($manager, 'api')->getJson('/api/v1/analytics/ai-usage');

        $response->assertOk();
        $this->assertSame(7, $response->json('data.total_replies'));

        $byProvider = collect($response->json('data.by_provider'))->keyBy('provider');
        $this->assertSame(4, $byProvider['internal']['count']);
        $this->assertSame(3, $byProvider['anthropic']['count']);

        $byStatus = $response->json('data.by_status');
        $this->assertSame(5, $byStatus['success']);
        $this->assertSame(1, $byStatus['error']);
        $this->assertSame(1, $byStatus['refused']);
    }

    public function test_ai_usage_never_exposes_tool_input_or_output_payloads_by_default(): void
    {
        AiUsageLog::factory()->create([
            'provider' => 'internal',
            'tool_name' => 'get_order_status',
            'tool_input' => ['order_number' => 'CC-20260101-ABCDEF'],
        ]);
        $manager = $this->makeUserWithRole('manager');

        $response = $this->actingAs($manager, 'api')->getJson('/api/v1/analytics/ai-usage');

        $response->assertOk();
        $this->assertStringNotContainsString('CC-20260101-ABCDEF', $response->getContent());
    }

    // --- AI cost monitoring: model/purpose/agent usage breakdown (CLAUDE.md §19) ---

    public function test_ai_usage_breaks_down_by_model_purpose_and_agent_type(): void
    {
        $inventoryAgent = $this->makeAiAgent(['agent_type' => AgentType::INVENTORY]);
        $salesAgent = $this->makeAiAgent(['agent_type' => AgentType::SALES]);

        AiUsageLog::factory()->create([
            'provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'purpose' => 'chat_reply',
            'input_tokens' => 100, 'output_tokens' => 50, 'cost_usd' => 0.002,
        ]);
        AiUsageLog::factory()->create([
            'user_id' => $inventoryAgent->id, 'model' => 'tool-router', 'purpose' => 'agent_tool_call', 'cost_usd' => 0,
        ]);
        AiUsageLog::factory()->count(2)->create([
            'user_id' => $salesAgent->id, 'model' => 'tool-router', 'purpose' => 'agent_tool_call', 'cost_usd' => 0,
        ]);

        $manager = $this->makeUserWithRole('manager');
        $response = $this->actingAs($manager, 'api')->getJson('/api/v1/analytics/ai-usage');

        $response->assertOk();
        $this->assertSame(100, $response->json('data.total_input_tokens'));
        $this->assertSame(50, $response->json('data.total_output_tokens'));

        $byModel = collect($response->json('data.by_model'))->keyBy('model');
        $this->assertSame(1, $byModel['claude-haiku-4-5']['count']);
        $this->assertSame(3, $byModel['tool-router']['count']);

        $byPurpose = collect($response->json('data.by_purpose'))->keyBy('purpose');
        $this->assertSame(1, $byPurpose['chat_reply']['count']);
        $this->assertSame(3, $byPurpose['agent_tool_call']['count']);

        $byAgentType = collect($response->json('data.by_agent_type'))->keyBy('agent_type');
        $this->assertSame(1, $byAgentType[AgentType::INVENTORY]['count']);
        $this->assertSame(2, $byAgentType[AgentType::SALES]['count']);
    }

    public function test_ai_usage_reports_configured_limits_alongside_todays_actual_usage(): void
    {
        config(['services.anthropic.daily_cost_limit_usd' => 5.0, 'services.anthropic.daily_request_limit' => 100]);
        AiUsageLog::factory()->create(['cost_usd' => 1.5]);

        $manager = $this->makeUserWithRole('manager');
        $response = $this->actingAs($manager, 'api')->getJson('/api/v1/analytics/ai-usage');

        $response->assertOk();
        $this->assertEquals(5.0, $response->json('data.limits.daily_cost_limit_usd'));
        $this->assertSame(100, $response->json('data.limits.daily_request_limit'));
        $this->assertEqualsWithDelta(1.5, $response->json('data.limits.daily_cost_spent_usd'), 0.0001);
        $this->assertSame(1, $response->json('data.limits.daily_requests_used'));
    }
}
