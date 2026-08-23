<?php

namespace Tests\Feature\Payments;

use App\Models\AuditLog;
use App\Models\CodRecord;
use App\Models\IdempotencyKey;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    /**
     * CSRF plus a fresh Idempotency-Key: every payment-sensitive mutation in
     * this file (refund, collect, verify, fail, return) requires both -
     * CLAUDE.md §13, enforced by the `idempotent` middleware on each route.
     */
    private function asCsrf($actor): TestResponse|\Illuminate\Foundation\Testing\TestCase
    {
        return $actor->withUnencryptedCookie('cc_csrf_token', 'x')
            ->withHeader('X-CSRF-Token', 'x')
            ->withHeader('Idempotency-Key', (string) Str::uuid());
    }

    private function asRefundActor($actor): TestResponse|\Illuminate\Foundation\Testing\TestCase
    {
        return $this->asCsrf($actor);
    }

    public function test_refunding_requires_orders_refund_permission(): void
    {
        $payment = Payment::factory()->create(['amount' => 1000, 'status' => 'paid']);
        $support = $this->makeUserWithRole('support');

        $this->asRefundActor($this->actingAs($support, 'api'))
            ->postJson("/api/v1/payments/{$payment->id}/refund", ['amount' => 500, 'reason' => 'damaged'])
            ->assertStatus(403);
    }

    public function test_a_partial_refund_marks_the_payment_partially_refunded_and_is_audit_logged(): void
    {
        $payment = Payment::factory()->create(['amount' => 1000, 'status' => 'paid']);
        $manager = $this->makeUserWithRole('manager');

        $response = $this->asRefundActor($this->actingAs($manager, 'api'))->postJson(
            "/api/v1/payments/{$payment->id}/refund",
            ['amount' => 400, 'reason' => 'damaged item']
        );

        $response->assertOk();
        $this->assertSame('partially_refunded', $payment->fresh()->status);
        $this->assertNotNull(AuditLog::where('action', 'payment.refunded')->first());
    }

    public function test_refunding_more_than_the_payment_amount_is_rejected(): void
    {
        $payment = Payment::factory()->create(['amount' => 1000, 'status' => 'paid']);
        $manager = $this->makeUserWithRole('manager');

        $this->asRefundActor($this->actingAs($manager, 'api'))
            ->postJson("/api/v1/payments/{$payment->id}/refund", ['amount' => 5000, 'reason' => 'x'])
            ->assertStatus(422);
    }

    public function test_a_full_refund_marks_the_payment_refunded(): void
    {
        $payment = Payment::factory()->create(['amount' => 1000, 'status' => 'paid']);
        $manager = $this->makeUserWithRole('manager');

        $this->asRefundActor($this->actingAs($manager, 'api'))
            ->postJson("/api/v1/payments/{$payment->id}/refund", ['amount' => 1000, 'reason' => 'cancelled'])
            ->assertOk();

        $this->assertSame('refunded', $payment->fresh()->status);
    }

    public function test_rider_can_collect_cod_but_not_verify_it(): void
    {
        $cod = CodRecord::factory()->create(['status' => 'awaiting_delivery', 'amount_due' => 1000]);
        $rider = $this->makeUserWithRole('delivery_rider');

        $collect = $this->asCsrf($this->actingAs($rider, 'api'))->patchJson(
            "/api/v1/cod-records/{$cod->id}/collect",
            ['amount_collected' => 1000]
        );
        $collect->assertOk();
        $this->assertSame('collected', $cod->fresh()->status);

        $this->asCsrf($this->actingAs($rider, 'api'))
            ->patchJson("/api/v1/cod-records/{$cod->id}/verify")
            ->assertStatus(403);
    }

    public function test_verifying_cod_marks_the_underlying_payment_paid(): void
    {
        $payment = Payment::factory()->create(['status' => 'pending', 'amount' => 1000]);
        $cod = CodRecord::factory()->create([
            'payment_id' => $payment->id, 'order_id' => $payment->order_id, 'status' => 'collected', 'amount_due' => 1000,
        ]);
        $orderManager = $this->makeUserWithRole('order_manager');

        $response = $this->asCsrf($this->actingAs($orderManager, 'api'))
            ->patchJson("/api/v1/cod-records/{$cod->id}/verify");

        $response->assertOk();
        $this->assertSame('deposited', $cod->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);

        $customerUser = $payment->fresh()->order->customer->user;
        $this->assertSame(1, $customerUser->notifications()->count());
    }

    public function test_cod_cannot_be_verified_before_being_collected(): void
    {
        $cod = CodRecord::factory()->create(['status' => 'awaiting_delivery']);
        $orderManager = $this->makeUserWithRole('order_manager');

        $this->asCsrf($this->actingAs($orderManager, 'api'))
            ->patchJson("/api/v1/cod-records/{$cod->id}/verify")
            ->assertStatus(422);
    }

    public function test_listing_cod_records_requires_cod_manage(): void
    {
        CodRecord::factory()->count(2)->create();
        $rider = $this->makeUserWithRole('delivery_rider');

        $this->actingAs($rider, 'api')
            ->getJson('/api/v1/cod-records')
            ->assertStatus(403);
    }

    public function test_order_manager_can_list_and_filter_cod_records(): void
    {
        CodRecord::factory()->create(['status' => 'awaiting_delivery']);
        CodRecord::factory()->create(['status' => 'collected']);
        $orderManager = $this->makeUserWithRole('order_manager');

        $this->actingAs($orderManager, 'api')
            ->getJson('/api/v1/cod-records?filter[status]=collected')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'collected');
    }

    public function test_a_single_cod_record_can_be_viewed_by_staff_with_cod_manage(): void
    {
        $cod = CodRecord::factory()->create();
        $manager = $this->makeUserWithRole('manager');

        $this->actingAs($manager, 'api')
            ->getJson("/api/v1/cod-records/{$cod->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $cod->id);
    }

    // --- Phase 09: payment status separation, COD failure/return, idempotency, concurrency ---

    public function test_collecting_cod_marks_the_underlying_payment_cod_collected_not_paid(): void
    {
        $payment = Payment::factory()->create(['status' => 'cod_pending', 'amount' => 1000]);
        $cod = CodRecord::factory()->create([
            'payment_id' => $payment->id, 'order_id' => $payment->order_id, 'status' => 'awaiting_delivery', 'amount_due' => 1000,
        ]);
        $rider = $this->makeUserWithRole('delivery_rider');

        $this->asCsrf($this->actingAs($rider, 'api'))
            ->patchJson("/api/v1/cod-records/{$cod->id}/collect", ['amount_collected' => 1000])
            ->assertOk();

        // "Delivered -> COD Collected", distinct from PAID until staff
        // verifies (order status and payment status never conflated).
        $this->assertSame('cod_collected', $payment->fresh()->status);
    }

    public function test_marking_cod_failed_requires_permission_and_marks_payment_failed(): void
    {
        $payment = Payment::factory()->create(['status' => 'cod_pending', 'amount' => 1000]);
        $cod = CodRecord::factory()->create([
            'payment_id' => $payment->id, 'order_id' => $payment->order_id, 'status' => 'awaiting_delivery',
        ]);

        $support = $this->makeUserWithRole('support');
        $this->asCsrf($this->actingAs($support, 'api'))
            ->patchJson("/api/v1/cod-records/{$cod->id}/fail", ['reason' => 'Customer unreachable'])
            ->assertStatus(403);

        $rider = $this->makeUserWithRole('delivery_rider');
        $response = $this->asCsrf($this->actingAs($rider, 'api'))
            ->patchJson("/api/v1/cod-records/{$cod->id}/fail", ['reason' => 'Customer unreachable']);

        $response->assertOk();
        $this->assertSame('failed_collection', $cod->fresh()->status);
        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_cod_can_only_be_marked_failed_while_awaiting_delivery(): void
    {
        $cod = CodRecord::factory()->create(['status' => 'collected']);
        $rider = $this->makeUserWithRole('delivery_rider');

        $this->asCsrf($this->actingAs($rider, 'api'))
            ->patchJson("/api/v1/cod-records/{$cod->id}/fail", ['reason' => 'x'])
            ->assertStatus(422);
    }

    public function test_returning_a_cod_order_marks_the_payment_failed(): void
    {
        $payment = Payment::factory()->create(['status' => 'cod_pending', 'amount' => 1000]);
        $cod = CodRecord::factory()->create([
            'payment_id' => $payment->id, 'order_id' => $payment->order_id, 'status' => 'awaiting_delivery',
        ]);
        $rider = $this->makeUserWithRole('delivery_rider');

        $this->asCsrf($this->actingAs($rider, 'api'))
            ->patchJson("/api/v1/cod-records/{$cod->id}/return", ['reason' => 'Customer refused'])
            ->assertOk();

        $this->assertSame('returned', $cod->fresh()->status);
        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_returning_a_cod_order_never_downgrades_an_already_settled_payment(): void
    {
        // An edge case (the order was already paid/verified before a later
        // return somehow gets recorded) - the payment must stay PAID, never
        // silently flip to FAILED.
        $payment = Payment::factory()->create(['status' => 'paid', 'amount' => 1000, 'paid_at' => now()]);
        $cod = CodRecord::factory()->create([
            'payment_id' => $payment->id, 'order_id' => $payment->order_id, 'status' => 'deposited',
        ]);
        $manager = $this->makeUserWithRole('manager');

        $this->asCsrf($this->actingAs($manager, 'api'))
            ->patchJson("/api/v1/cod-records/{$cod->id}/return", ['reason' => 'Return after the fact'])
            ->assertOk();

        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_a_retried_collect_request_with_the_same_idempotency_key_replays_instead_of_double_processing(): void
    {
        $payment = Payment::factory()->create(['status' => 'cod_pending', 'amount' => 1000]);
        $cod = CodRecord::factory()->create([
            'payment_id' => $payment->id, 'order_id' => $payment->order_id, 'status' => 'awaiting_delivery', 'amount_due' => 1000,
        ]);
        $rider = $this->makeUserWithRole('delivery_rider');
        $key = (string) Str::uuid();

        $actor = $this->actingAs($rider, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->withHeader('Idempotency-Key', $key);

        $first = $actor->patchJson("/api/v1/cod-records/{$cod->id}/collect", ['amount_collected' => 1000]);
        $first->assertOk();

        $second = $actor->patchJson("/api/v1/cod-records/{$cod->id}/collect", ['amount_collected' => 1000]);
        $second->assertOk();
        $second->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        // Collected exactly once - no duplicate amount_collected accumulation.
        $this->assertSame('collected', $cod->fresh()->status);
        $this->assertSame(1, IdempotencyKey::count());
    }

    /**
     * The concurrency guard that actually matters under real parallel
     * requests: two callers racing to collect the *same* COD order with
     * *different* idempotency keys (so neither short-circuits into a
     * replay) - the second must be rejected by the status check inside the
     * transaction, not silently succeed a second time.
     */
    public function test_two_concurrent_collect_attempts_with_different_keys_the_second_is_rejected(): void
    {
        $cod = CodRecord::factory()->create(['status' => 'awaiting_delivery', 'amount_due' => 1000]);
        $riderA = $this->makeUserWithRole('delivery_rider');
        $riderB = $this->makeUserWithRole('delivery_rider');

        $this->asCsrf($this->actingAs($riderA, 'api'))
            ->patchJson("/api/v1/cod-records/{$cod->id}/collect", ['amount_collected' => 1000])
            ->assertOk();

        $this->asCsrf($this->actingAs($riderB, 'api'))
            ->patchJson("/api/v1/cod-records/{$cod->id}/collect", ['amount_collected' => 1000])
            ->assertStatus(422);

        $this->assertSame('collected', $cod->fresh()->status);
        $this->assertSame($riderA->id, $cod->fresh()->collected_by);
    }

    public function test_collect_without_an_idempotency_key_header_is_rejected(): void
    {
        $cod = CodRecord::factory()->create(['status' => 'awaiting_delivery', 'amount_due' => 1000]);
        $rider = $this->makeUserWithRole('delivery_rider');

        $rider = $this->actingAs($rider, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x');

        $rider->patchJson("/api/v1/cod-records/{$cod->id}/collect", ['amount_collected' => 1000])
            ->assertStatus(400);
    }

    public function test_payments_table_never_persists_raw_payment_credentials(): void
    {
        // Documentation-as-test: only a gateway name and its own opaque
        // reference token are ever stored (CLAUDE.md §5) - none of these
        // columns should ever exist on this table.
        $forbidden = ['pin', 'otp', 'password', 'card_number', 'cvv', 'card_cvv', 'api_secret', 'api_key'];

        foreach ($forbidden as $column) {
            $this->assertFalse(
                Schema::hasColumn('payments', $column),
                "payments table must never have a '{$column}' column"
            );
        }
    }
}
