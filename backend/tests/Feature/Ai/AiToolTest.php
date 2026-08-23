<?php

namespace Tests\Feature\Ai;

use App\Models\AiUsageLog;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class AiToolTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    public function test_the_ai_agent_can_check_product_availability(): void
    {
        $variant = ProductVariant::factory()->create();
        $variant->inventory()->create(['quantity_on_hand' => 5]);
        $agent = $this->makeAiAgent();

        $response = $this->actingAs($agent, 'api')->postJson('/api/v1/ai/tools/invoke', [
            'tool' => 'check_product_availability',
            'input' => ['product_variant_id' => $variant->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('output.available', true);
        $this->assertNotNull(AiUsageLog::where('tool_name', 'check_product_availability')->where('status', 'success')->first());
    }

    public function test_a_disallowed_tool_is_refused_and_logged(): void
    {
        $agent = $this->makeAiAgent();

        $response = $this->actingAs($agent, 'api')->postJson('/api/v1/ai/tools/invoke', [
            'tool' => 'delete_all_orders',
        ]);

        $response->assertStatus(422);
        $this->assertNotNull(AiUsageLog::where('tool_name', 'delete_all_orders')->where('status', 'refused')->first());
    }

    public function test_a_human_super_admin_cannot_invoke_ai_tools(): void
    {
        $superAdmin = $this->makeUserWithRole('super_admin');

        $this->actingAs($superAdmin, 'api')
            ->postJson('/api/v1/ai/tools/invoke', ['tool' => 'get_order_status', 'input' => ['order_number' => 'X']])
            ->assertStatus(403);
    }

    public function test_get_order_status_never_returns_customer_pii(): void
    {
        $order = Order::factory()->create(['contact_phone' => '03001234567']);
        $agent = $this->makeAiAgent();

        $response = $this->actingAs($agent, 'api')->postJson('/api/v1/ai/tools/invoke', [
            'tool' => 'get_order_status',
            'input' => ['order_number' => $order->order_number],
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('03001234567', $response->getContent());
    }

    public function test_invalid_tool_input_is_rejected_by_schema_validation(): void
    {
        $agent = $this->makeAiAgent();

        $this->actingAs($agent, 'api')
            ->postJson('/api/v1/ai/tools/invoke', ['tool' => 'check_product_availability', 'input' => ['product_variant_id' => 999999]])
            ->assertStatus(422);
    }
}
