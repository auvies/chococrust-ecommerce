<?php

namespace Tests\Feature\Delivery;

use App\Models\CodRecord;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    public function test_order_manager_can_create_a_delivery_for_an_order(): void
    {
        $order = Order::factory()->create();
        $manager = $this->makeUserWithRole('order_manager');

        $response = $this->actingAs($manager, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->postJson("/api/v1/orders/{$order->id}/delivery", ['type' => 'local']);

        $response->assertCreated();
    }

    public function test_a_rider_only_sees_deliveries_assigned_to_them(): void
    {
        $rider = $this->makeUserWithRole('delivery_rider');
        $other = $this->makeUserWithRole('delivery_rider');
        Delivery::factory()->create(['rider_id' => $rider->id]);
        Delivery::factory()->create(['rider_id' => $other->id]);

        $response = $this->actingAs($rider, 'api')->getJson('/api/v1/deliveries');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_a_rider_cannot_update_a_delivery_assigned_to_someone_else(): void
    {
        $rider = $this->makeUserWithRole('delivery_rider');
        $delivery = Delivery::factory()->create(['rider_id' => $this->makeUserWithRole('delivery_rider')->id]);

        $this->actingAs($rider, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->patchJson("/api/v1/deliveries/{$delivery->id}/status", ['status' => 'picked_up'])
            ->assertStatus(403);
    }

    public function test_updating_delivery_status_records_a_tracking_event(): void
    {
        $rider = $this->makeUserWithRole('delivery_rider');
        $delivery = Delivery::factory()->create(['rider_id' => $rider->id]);

        $response = $this->actingAs($rider, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->patchJson("/api/v1/deliveries/{$delivery->id}/status", ['status' => 'delivered']);

        $response->assertOk();
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertCount(1, $delivery->trackingEvents);
        $this->assertNotNull($delivery->fresh()->delivered_at);
    }

    public function test_updating_delivery_status_notifies_the_customer(): void
    {
        $rider = $this->makeUserWithRole('delivery_rider');
        $delivery = Delivery::factory()->create(['rider_id' => $rider->id]);

        $this->actingAs($rider, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->patchJson("/api/v1/deliveries/{$delivery->id}/status", ['status' => 'out_for_delivery'])
            ->assertOk();

        $customerUser = $delivery->order->customer->user;
        $this->assertSame(1, $customerUser->notifications()->count());
    }

    // --- Phase 09: delivery attempts, failure reasons, and COD/payment sync on return ---

    public function test_moving_to_out_for_delivery_increments_the_attempt_counter(): void
    {
        $rider = $this->makeUserWithRole('delivery_rider');
        $delivery = Delivery::factory()->create(['rider_id' => $rider->id, 'delivery_attempts' => 0]);

        $actor = $this->actingAs($rider, 'api')->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x');
        $actor->patchJson("/api/v1/deliveries/{$delivery->id}/status", ['status' => 'out_for_delivery'])->assertOk();
        $this->assertSame(1, $delivery->fresh()->delivery_attempts);

        // A failed attempt, reassigned and tried again - a second attempt.
        $actor->patchJson("/api/v1/deliveries/{$delivery->id}/status", ['status' => 'failed', 'failure_reason' => 'Customer unavailable'])->assertOk();
        $actor->patchJson("/api/v1/deliveries/{$delivery->id}/status", ['status' => 'out_for_delivery'])->assertOk();

        $this->assertSame(2, $delivery->fresh()->delivery_attempts);
    }

    public function test_a_failure_reason_is_required_when_marking_a_delivery_failed_or_returned(): void
    {
        $rider = $this->makeUserWithRole('delivery_rider');
        $delivery = Delivery::factory()->create(['rider_id' => $rider->id]);

        $this->actingAs($rider, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->patchJson("/api/v1/deliveries/{$delivery->id}/status", ['status' => 'failed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('failure_reason');
    }

    public function test_a_failed_delivery_records_its_reason(): void
    {
        $rider = $this->makeUserWithRole('delivery_rider');
        $delivery = Delivery::factory()->create(['rider_id' => $rider->id]);

        $this->actingAs($rider, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->patchJson("/api/v1/deliveries/{$delivery->id}/status", ['status' => 'failed', 'failure_reason' => 'Customer refused'])
            ->assertOk()
            ->assertJsonPath('data.failure_reason', 'Customer refused');

        $this->assertSame('Customer refused', $delivery->fresh()->failure_reason);
    }

    public function test_returning_a_delivery_for_a_cod_order_fails_its_payment_too(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->create(['order_id' => $order->id, 'status' => 'cod_pending', 'amount' => 1000]);
        CodRecord::factory()->create([
            'order_id' => $order->id, 'payment_id' => $payment->id, 'status' => 'awaiting_delivery', 'amount_due' => 1000,
        ]);
        $rider = $this->makeUserWithRole('delivery_rider');
        $delivery = Delivery::factory()->create(['order_id' => $order->id, 'rider_id' => $rider->id]);

        $this->actingAs($rider, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->patchJson("/api/v1/deliveries/{$delivery->id}/status", ['status' => 'returned', 'failure_reason' => 'Customer refused delivery'])
            ->assertOk();

        $this->assertSame('returned', $delivery->fresh()->status);
        $this->assertSame('returned', CodRecord::where('order_id', $order->id)->value('status'));
        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_returning_a_delivery_for_a_non_cod_order_does_not_error(): void
    {
        // No cod_records row exists at all - the sync must be a no-op, not a crash.
        $order = Order::factory()->create();
        $rider = $this->makeUserWithRole('delivery_rider');
        $delivery = Delivery::factory()->create(['order_id' => $order->id, 'rider_id' => $rider->id]);

        $this->actingAs($rider, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->patchJson("/api/v1/deliveries/{$delivery->id}/status", ['status' => 'returned', 'failure_reason' => 'Address unreachable'])
            ->assertOk();

        $this->assertSame('returned', $delivery->fresh()->status);
    }
}
