<?php

namespace Tests\Feature\Agents;

use App\Models\AgentPendingAction;
use App\Models\AiUsageLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Agents\AgentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class AgentToolGatewayTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    private function invoke($agent, string $tool, array $input = [])
    {
        return $this->actingAs($agent, 'api')->postJson('/api/v1/ai/tools/invoke', ['tool' => $tool, 'input' => $input]);
    }

    // --- Agent-type authorization scoping ---

    public function test_an_agent_of_the_wrong_type_is_refused_a_scoped_tool(): void
    {
        $variant = ProductVariant::factory()->create();
        $variant->inventory()->create(['quantity_on_hand' => 3]);
        // A sales agent, not an inventory/analytics/dispatch/order agent - get_inventory_level is scoped away from it.
        $agent = $this->makeAiAgent(['agent_type' => AgentType::MARKETING]);

        $response = $this->invoke($agent, 'get_inventory_level', ['product_variant_id' => $variant->id]);

        $response->assertStatus(422);
        $this->assertNotNull(AiUsageLog::where('tool_name', 'get_inventory_level')->where('status', 'refused')->first());
    }

    public function test_an_agent_of_the_correct_type_can_use_a_scoped_tool(): void
    {
        $variant = ProductVariant::factory()->create();
        $variant->inventory()->create(['quantity_on_hand' => 7]);
        $agent = $this->makeAiAgent(['agent_type' => AgentType::INVENTORY]);

        $response = $this->invoke($agent, 'get_inventory_level', ['product_variant_id' => $variant->id]);

        $response->assertOk();
        $response->assertJsonPath('output.quantity_on_hand', 7);
    }

    public function test_unrestricted_tools_still_work_for_an_agent_with_no_type_set(): void
    {
        // Backward compatibility with the pre-Phase-13 chatbot tool surface -
        // an agent identity with no agent_type at all can still use the two
        // originally-unrestricted tools.
        $variant = ProductVariant::factory()->create();
        $variant->inventory()->create(['quantity_on_hand' => 5]);
        $agent = $this->makeAiAgent(); // agent_type left null

        $this->invoke($agent, 'check_product_availability', ['product_variant_id' => $variant->id])
            ->assertOk()
            ->assertJsonPath('output.available', true);
    }

    public function test_a_scoped_tool_is_refused_to_an_agent_with_no_type_set(): void
    {
        $agent = $this->makeAiAgent(); // agent_type null - not inventory_agent/sales_agent

        $variant = ProductVariant::factory()->create();

        $this->invoke($agent, 'get_inventory_level', ['product_variant_id' => $variant->id])->assertStatus(422);
    }

    // --- High-risk operations require human approval, not immediate execution ---

    public function test_a_high_risk_refund_request_never_executes_immediately(): void
    {
        $payment = Payment::factory()->create(['status' => 'paid', 'amount' => 1000]);
        $agent = $this->makeAiAgent(['agent_type' => AgentType::PAYMENT]);

        $response = $this->invoke($agent, 'request_refund', [
            'payment_id' => $payment->id, 'amount' => 500, 'reason' => 'Customer requested',
        ]);

        $response->assertOk();
        $response->assertJsonPath('output.status', 'pending_approval');
        // The payment itself must be completely untouched - no refund actually happened.
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertDatabaseCount('agent_pending_actions', 1);
        $this->assertSame('pending', AgentPendingAction::first()->status);
    }

    public function test_a_high_risk_order_cancellation_request_never_executes_immediately(): void
    {
        $order = \App\Models\Order::factory()->create(['status' => 'pending']);
        $agent = $this->makeAiAgent(['agent_type' => AgentType::ORDER]);

        $response = $this->invoke($agent, 'request_order_cancellation', [
            'order_number' => $order->order_number, 'reason' => 'Out of stock',
        ]);

        $response->assertOk();
        $response->assertJsonPath('output.status', 'pending_approval');
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_high_risk_tools_are_also_scoped_by_agent_type(): void
    {
        $payment = Payment::factory()->create();
        $agent = $this->makeAiAgent(['agent_type' => AgentType::MARKETING]);

        $this->invoke($agent, 'request_refund', ['payment_id' => $payment->id, 'amount' => 10, 'reason' => 'x'])
            ->assertStatus(422);
        $this->assertDatabaseCount('agent_pending_actions', 0);
    }

    // --- Unknown tool / validation ---

    public function test_an_unregistered_tool_is_refused_and_logged(): void
    {
        $agent = $this->makeAiAgent();

        $response = $this->invoke($agent, 'delete_all_customers');

        $response->assertStatus(422);
        $this->assertNotNull(AiUsageLog::where('tool_name', 'delete_all_customers')->where('status', 'refused')->first());
    }

    public function test_invalid_input_is_rejected_by_the_strict_schema_before_any_business_logic_runs(): void
    {
        $agent = $this->makeAiAgent(['agent_type' => AgentType::PAYMENT]);

        $this->invoke($agent, 'request_refund', ['payment_id' => 999999, 'amount' => -5])
            ->assertStatus(422);
        $this->assertDatabaseCount('agent_pending_actions', 0);
    }

    // --- No arbitrary SQL access: an injection-shaped value is just an ordinary invalid value ---

    public function test_a_sql_injection_shaped_order_number_is_handled_as_an_ordinary_lookup_miss(): void
    {
        $agent = $this->makeAiAgent();

        $response = $this->invoke($agent, 'get_order_status', ['order_number' => "'; DROP TABLE orders; --"]);

        // Rejected by schema validation (not a real order number) - not a
        // 500, and no table was touched.
        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_a_human_staff_account_cannot_invoke_agent_tools_even_with_every_other_permission(): void
    {
        $superAdmin = $this->makeUserWithRole('super_admin');

        $this->invoke($superAdmin, 'get_order_status', ['order_number' => 'CC-1'])->assertStatus(403);
    }

    // --- Configurable usage limits (App\Services\Ai\AiUsageLimiter) ---

    public function test_an_agent_over_its_hourly_tool_call_budget_is_refused_and_logged(): void
    {
        config(['services.anthropic.max_agent_tool_calls_per_hour' => 2]);
        $agent = $this->makeAiAgent();
        $variant = ProductVariant::factory()->create();
        $variant->inventory()->create(['quantity_on_hand' => 5]);

        // First two calls consume the budget.
        $this->invoke($agent, 'check_product_availability', ['product_variant_id' => $variant->id])->assertOk();
        $this->invoke($agent, 'check_product_availability', ['product_variant_id' => $variant->id])->assertOk();

        // The third, within the same hour, is refused before the tool ever runs.
        $response = $this->invoke($agent, 'check_product_availability', ['product_variant_id' => $variant->id]);

        $response->assertStatus(422);
        $this->assertSame(3, AiUsageLog::where('user_id', $agent->id)->where('purpose', 'agent_tool_call')->count());
        $this->assertSame('refused', AiUsageLog::where('user_id', $agent->id)->latest('id')->first()->status);
    }

    public function test_the_hourly_budget_is_scoped_per_agent_not_global(): void
    {
        config(['services.anthropic.max_agent_tool_calls_per_hour' => 1]);
        $agentA = $this->makeAiAgent();
        $agentB = $this->makeAiAgent();
        $variant = ProductVariant::factory()->create();
        $variant->inventory()->create(['quantity_on_hand' => 5]);

        $this->invoke($agentA, 'check_product_availability', ['product_variant_id' => $variant->id])->assertOk();
        // Agent A is now over budget, but Agent B's own budget is untouched.
        $this->invoke($agentA, 'check_product_availability', ['product_variant_id' => $variant->id])->assertStatus(422);
        $this->invoke($agentB, 'check_product_availability', ['product_variant_id' => $variant->id])->assertOk();
    }

    public function test_a_tripped_global_daily_cost_limit_refuses_every_agent_tool_call(): void
    {
        config(['services.anthropic.daily_cost_limit_usd' => 0.01]);
        AiUsageLog::factory()->create(['cost_usd' => 0.02, 'created_at' => now()]);
        $agent = $this->makeAiAgent();
        $variant = ProductVariant::factory()->create();
        $variant->inventory()->create(['quantity_on_hand' => 5]);

        $this->invoke($agent, 'check_product_availability', ['product_variant_id' => $variant->id])->assertStatus(422);
    }
}
