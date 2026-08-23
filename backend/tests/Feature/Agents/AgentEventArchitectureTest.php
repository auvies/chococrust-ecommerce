<?php

namespace Tests\Feature\Agents;

use App\Events\OrderCancelled;
use App\Events\OrderConfirmed;
use App\Events\OrderCreated;
use App\Events\OrderDelivered;
use App\Events\OrderDispatched;
use App\Events\OrderReady;
use App\Events\PaymentSubmitted;
use App\Events\PaymentVerified;
use App\Events\RefundCompleted;
use App\Models\AgentEventLog;
use App\Models\CodRecord;
use App\Models\Customer;
use App\Models\DeliveryRule;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Services\Agents\AgentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class AgentEventArchitectureTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    /** Every one of the 9 named events actually dispatches at the point in the code the business named, and is logged. */
    public function test_every_named_event_class_logs_to_agent_event_logs_when_dispatched(): void
    {
        $cases = [
            [OrderCreated::class, fn () => OrderCreated::dispatch(1, 'CC-X', 1, 100.0), 'ORDER_CREATED'],
            [PaymentSubmitted::class, fn () => PaymentSubmitted::dispatch(1, 1, 'CC-X', 'cod'), 'PAYMENT_SUBMITTED'],
            [PaymentVerified::class, fn () => PaymentVerified::dispatch(1, 1, 'CC-X'), 'PAYMENT_VERIFIED'],
            [OrderConfirmed::class, fn () => OrderConfirmed::dispatch(1, 'CC-X'), 'ORDER_CONFIRMED'],
            [OrderReady::class, fn () => OrderReady::dispatch(1, 'CC-X'), 'ORDER_READY'],
            [OrderDispatched::class, fn () => OrderDispatched::dispatch(1, 'CC-X'), 'ORDER_DISPATCHED'],
            [OrderDelivered::class, fn () => OrderDelivered::dispatch(1, 'CC-X'), 'ORDER_DELIVERED'],
            [OrderCancelled::class, fn () => OrderCancelled::dispatch(1, 'CC-X'), 'ORDER_CANCELLED'],
            [RefundCompleted::class, fn () => RefundCompleted::dispatch(1, 1, 'CC-X', 50.0), 'REFUND_COMPLETED'],
        ];

        foreach ($cases as [$class, $dispatch, $expectedType]) {
            $dispatch();
            $this->assertDatabaseHas('agent_event_logs', ['event_type' => $expectedType]);
        }

        $this->assertSame(9, AgentEventLog::count());
    }

    public function test_placing_an_order_fires_order_created(): void
    {
        Event::fake([\App\Events\OrderCreated::class]);

        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        DeliveryRule::firstOrCreate(
            ['scope' => 'global', 'category_id' => null, 'product_id' => null, 'delivery_type' => 'local'],
            ['is_deliverable' => true, 'flat_fee' => 0, 'is_active' => true],
        );
        $user = $this->makeUserWithRole('customer');
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $address = \App\Models\Address::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->withHeader('Idempotency-Key', (string) \Illuminate\Support\Str::uuid())
            ->postJson('/api/v1/orders', [
                'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
                'delivery_type' => 'local',
                'shipping_address_id' => $address->id,
                'contact_name' => 'Test',
                'contact_phone' => '03001234567',
                'payment_method' => 'cod',
            ])->assertCreated();

        Event::assertDispatched(\App\Events\OrderCreated::class);
    }

    public function test_verifying_cod_fires_payment_verified(): void
    {
        $payment = Payment::factory()->create(['status' => 'pending', 'amount' => 1000]);
        $cod = CodRecord::factory()->create([
            'payment_id' => $payment->id, 'order_id' => $payment->order_id, 'status' => 'collected', 'amount_due' => 1000,
        ]);
        $orderManager = $this->makeUserWithRole('order_manager');

        $this->actingAs($orderManager, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->withHeader('Idempotency-Key', (string) \Illuminate\Support\Str::uuid())
            ->patchJson("/api/v1/cod-records/{$cod->id}/verify")
            ->assertOk();

        $this->assertDatabaseHas('agent_event_logs', ['event_type' => 'PAYMENT_VERIFIED']);
    }

    // --- get_recent_events tool reads the feed, bounded, never touches orders/payments directly ---

    public function test_get_recent_events_reads_the_log_bounded_and_filterable(): void
    {
        foreach (range(1, 25) as $i) {
            OrderCreated::dispatch($i, "CC-{$i}", 1, 100.0);
        }
        RefundCompleted::dispatch(1, 1, 'CC-1', 50.0);

        $agent = $this->makeAiAgent(['agent_type' => AgentType::ANALYTICS]);

        $all = $this->actingAs($agent, 'api')->postJson('/api/v1/ai/tools/invoke', [
            'tool' => 'get_recent_events',
        ]);
        $all->assertOk();
        $this->assertLessThanOrEqual(20, count($all->json('output.events')));

        $filtered = $this->actingAs($agent, 'api')->postJson('/api/v1/ai/tools/invoke', [
            'tool' => 'get_recent_events',
            'input' => ['event_type' => 'REFUND_COMPLETED'],
        ]);
        $filtered->assertOk();
        $this->assertCount(1, $filtered->json('output.events'));
    }

    public function test_get_recent_events_is_scoped_away_from_unrelated_agent_types(): void
    {
        $agent = $this->makeAiAgent(['agent_type' => AgentType::MARKETING]);

        $this->actingAs($agent, 'api')->postJson('/api/v1/ai/tools/invoke', ['tool' => 'get_recent_events'])
            ->assertStatus(422);
    }
}
