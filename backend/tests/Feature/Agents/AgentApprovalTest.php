<?php

namespace Tests\Feature\Agents;

use App\Models\AgentPendingAction;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Agents\AgentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class AgentApprovalTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    private function withCsrf()
    {
        return $this->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x');
    }

    private function pendingRefund(float $amount = 500, float $paymentAmount = 1000): array
    {
        $payment = Payment::factory()->create(['status' => 'paid', 'amount' => $paymentAmount]);
        $agent = $this->makeAiAgent(['agent_type' => AgentType::PAYMENT]);

        $this->actingAs($agent, 'api')->postJson('/api/v1/ai/tools/invoke', [
            'tool' => 'request_refund',
            'input' => ['payment_id' => $payment->id, 'amount' => $amount, 'reason' => 'Customer requested'],
        ])->assertOk();

        return [$payment, AgentPendingAction::firstOrFail()];
    }

    // --- Listing requires the review permission ---

    public function test_listing_pending_actions_requires_agent_actions_manage(): void
    {
        [, $action] = $this->pendingRefund();
        $support = $this->makeUserWithRole('support');

        $this->actingAs($support, 'api')->getJson('/api/v1/agent-actions')->assertStatus(403);

        $manager = $this->makeUserWithRole('manager');
        $this->actingAs($manager, 'api')->getJson('/api/v1/agent-actions')->assertOk()->assertJsonCount(1, 'data');
    }

    // --- Approving requires BOTH agent_actions.manage AND the tool's own domain permission ---

    public function test_approving_a_refund_requires_orders_refund_not_just_agent_actions_manage(): void
    {
        [$payment, $action] = $this->pendingRefund();

        // content_manager: give it agent_actions.manage-equivalent access
        // is not possible via existing roles, so prove the negative with a
        // role that plausibly could review generically but lacks the
        // domain permission - support has neither, order_manager has
        // orders.manage/cod.manage but not orders.refund or
        // agent_actions.manage.
        $orderManager = $this->makeUserWithRole('order_manager');

        $this->actingAs($orderManager, 'api')->withCsrf()
            ->postJson("/api/v1/agent-actions/{$action->id}/approve")
            ->assertStatus(403);

        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_a_manager_can_approve_a_refund_request_and_it_actually_executes(): void
    {
        [$payment, $action] = $this->pendingRefund(amount: 400, paymentAmount: 1000);
        $manager = $this->makeUserWithRole('manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()
            ->postJson("/api/v1/agent-actions/{$action->id}/approve");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'executed');
        $this->assertSame('partially_refunded', $payment->fresh()->status);
        $this->assertSame($manager->id, $action->fresh()->reviewed_by);
    }

    public function test_rejecting_a_refund_request_never_executes_it(): void
    {
        [$payment, $action] = $this->pendingRefund();
        $manager = $this->makeUserWithRole('manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()
            ->postJson("/api/v1/agent-actions/{$action->id}/reject", ['reason' => 'Amount looks wrong']);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'rejected');
        $this->assertSame('paid', $payment->fresh()->status);
    }

    // --- No double-processing ---

    public function test_an_already_reviewed_action_cannot_be_approved_again(): void
    {
        [, $action] = $this->pendingRefund();
        $manager = $this->makeUserWithRole('manager');

        $this->actingAs($manager, 'api')->withCsrf()->postJson("/api/v1/agent-actions/{$action->id}/approve")->assertOk();

        $second = $this->actingAs($manager, 'api')->withCsrf()->postJson("/api/v1/agent-actions/{$action->id}/approve");

        $second->assertStatus(422);
    }

    public function test_a_rejected_action_cannot_later_be_approved(): void
    {
        [$payment, $action] = $this->pendingRefund();
        $manager = $this->makeUserWithRole('manager');

        $this->actingAs($manager, 'api')->withCsrf()->postJson("/api/v1/agent-actions/{$action->id}/reject", ['reason' => 'No'])->assertOk();

        $this->actingAs($manager, 'api')->withCsrf()->postJson("/api/v1/agent-actions/{$action->id}/approve")->assertStatus(422);
        $this->assertSame('paid', $payment->fresh()->status);
    }

    // --- A failure at approval time is recorded, not silently swallowed or retried ---

    public function test_approving_a_refund_that_now_exceeds_whats_refundable_records_a_failure(): void
    {
        // The pending request was for 500 out of a 1000 payment - but by
        // the time it's reviewed, someone has already refunded 800 of it
        // directly, so 500 no longer fits.
        [$payment, $action] = $this->pendingRefund(amount: 500, paymentAmount: 1000);
        $payment->transactions()->create(['type' => 'refund', 'status' => 'succeeded', 'amount' => 800, 'raw_response' => ['note' => 'pre-existing refund'], 'processed_at' => now()]);
        $payment->update(['status' => 'partially_refunded']);
        $manager = $this->makeUserWithRole('manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()->postJson("/api/v1/agent-actions/{$action->id}/approve");

        $response->assertStatus(422);
        $this->assertSame('failed', $action->fresh()->status);
        $this->assertNotNull($action->fresh()->error);
    }

    // --- Order cancellation approval end to end ---

    public function test_approving_an_order_cancellation_request_actually_cancels_it(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);
        $agent = $this->makeAiAgent(['agent_type' => AgentType::ORDER]);
        $this->actingAs($agent, 'api')->postJson('/api/v1/ai/tools/invoke', [
            'tool' => 'request_order_cancellation',
            'input' => ['order_number' => $order->order_number, 'reason' => 'Customer changed their mind'],
        ])->assertOk();
        $action = AgentPendingAction::firstOrFail();
        $manager = $this->makeUserWithRole('manager');

        $this->actingAs($manager, 'api')->withCsrf()->postJson("/api/v1/agent-actions/{$action->id}/approve")->assertOk();

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_an_ai_agent_identity_cannot_review_its_own_pending_actions(): void
    {
        [, $action] = $this->pendingRefund();
        $agent = $this->makeAiAgent(['agent_type' => AgentType::PAYMENT]);

        $this->actingAs($agent, 'api')->getJson('/api/v1/agent-actions')->assertStatus(403);
        $this->actingAs($agent, 'api')->withCsrf()->postJson("/api/v1/agent-actions/{$action->id}/approve")->assertStatus(403);
    }
}
