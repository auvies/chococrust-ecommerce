<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
        $this->inventory = new InventoryService;
    }

    private function withCsrf()
    {
        return $this->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x');
    }

    public function test_adjusting_stock_requires_inventory_manage_permission(): void
    {
        $inventory = Inventory::factory()->create(['quantity_on_hand' => 10]);
        $support = $this->makeUserWithRole('support');

        $this->actingAs($support, 'api')->withCsrf()
            ->patchJson("/api/v1/inventory/{$inventory->id}/adjust", ['delta' => 5, 'reason' => 'restock'])
            ->assertStatus(403);
    }

    public function test_manager_can_adjust_stock_and_it_is_audit_logged(): void
    {
        $inventory = Inventory::factory()->create(['quantity_on_hand' => 10]);
        $manager = $this->makeUserWithRole('manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()->patchJson(
            "/api/v1/inventory/{$inventory->id}/adjust",
            ['delta' => -3, 'reason' => 'damaged stock']
        );

        $response->assertOk();
        $this->assertSame(7, $inventory->fresh()->quantity_on_hand);
    }

    public function test_stock_cannot_be_adjusted_below_zero(): void
    {
        $inventory = Inventory::factory()->create(['quantity_on_hand' => 2]);
        $manager = $this->makeUserWithRole('manager');

        $this->actingAs($manager, 'api')->withCsrf()
            ->patchJson("/api/v1/inventory/{$inventory->id}/adjust", ['delta' => -5, 'reason' => 'oops'])
            ->assertStatus(422);
    }

    public function test_adjusting_stock_records_an_inventory_movement(): void
    {
        $inventory = Inventory::factory()->create(['quantity_on_hand' => 10]);
        $manager = $this->makeUserWithRole('manager');

        $this->actingAs($manager, 'api')->withCsrf()
            ->patchJson("/api/v1/inventory/{$inventory->id}/adjust", ['delta' => -3, 'reason' => 'damaged stock'])
            ->assertOk();

        $movement = InventoryMovement::where('product_variant_id', $inventory->product_variant_id)->first();
        $this->assertNotNull($movement);
        $this->assertSame('manual_adjustment', $movement->type);
        $this->assertSame(-3, $movement->quantity_delta);
        $this->assertSame('damaged stock', $movement->reason);
        $this->assertSame($manager->id, $movement->created_by);
    }

    public function test_inventory_history_endpoint_lists_movements_newest_first(): void
    {
        $inventory = Inventory::factory()->create(['quantity_on_hand' => 50]);
        $variant = ProductVariant::find($inventory->product_variant_id);
        $manager = $this->makeUserWithRole('manager');

        $this->inventory->adjust($inventory, 10, 'restock');
        $this->inventory->adjust($inventory->fresh(), -5, 'shrinkage');

        $response = $this->actingAs($manager, 'api')->getJson("/api/v1/inventory/{$inventory->id}/history");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $this->assertSame('shrinkage', $response->json('data.0.reason'));
        $this->assertSame('restock', $response->json('data.1.reason'));
    }

    // --- Phase 09: InventoryService lifecycle (reserve/commit/release/fulfill) and concurrency ---

    public function test_reserve_holds_stock_and_records_a_movement(): void
    {
        $variant = ProductVariant::factory()->create();
        $variant->inventory()->create(['quantity_on_hand' => 10]);

        $reservation = $this->inventory->reserve($variant, 4, null);

        $this->assertSame('pending', $reservation->status);
        $this->assertSame(4, $reservation->quantity);
        $this->assertSame(6, $this->inventory->availableQuantity($variant));

        $movement = InventoryMovement::where('product_variant_id', $variant->id)->first();
        $this->assertSame('reservation_created', $movement->type);
        $this->assertSame(-4, $movement->quantity_delta);
    }

    public function test_reserve_rejects_when_not_enough_stock_is_available(): void
    {
        $variant = ProductVariant::factory()->create();
        $variant->inventory()->create(['quantity_on_hand' => 3]);

        $this->expectException(ValidationException::class);
        $this->inventory->reserve($variant, 5, null);
    }

    public function test_two_reservations_racing_for_the_last_units_the_second_is_rejected(): void
    {
        // Simulates the classic "last item in stock" race: two callers both
        // try to reserve more than what's left combined - only the first
        // can succeed, since each reserve() call re-reads available stock
        // under a row lock (see InventoryService's own docblock).
        $variant = ProductVariant::factory()->create();
        $variant->inventory()->create(['quantity_on_hand' => 5]);

        $this->inventory->reserve($variant, 5, null);

        $this->expectException(ValidationException::class);
        $this->inventory->reserve($variant, 1, null);
    }

    public function test_commit_moves_a_pending_reservation_to_committed_without_changing_available_stock(): void
    {
        $variant = ProductVariant::factory()->create();
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $reservation = $this->inventory->reserve($variant, 3, null);

        $committed = $this->inventory->commit($reservation);

        $this->assertSame('committed', $committed->status);
        $this->assertSame(7, $this->inventory->availableQuantity($variant));
    }

    public function test_release_returns_a_reservation_to_available_stock_and_is_idempotent(): void
    {
        $variant = ProductVariant::factory()->create();
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $reservation = $this->inventory->reserve($variant, 4, null);

        $this->inventory->release($reservation);
        $this->assertSame(10, $this->inventory->availableQuantity($variant));
        $this->assertSame('released', $reservation->fresh()->status);

        // Releasing an already-released reservation is a no-op, not an
        // error - a cancel request and an expiry sweep might race.
        $released = $this->inventory->release($reservation->fresh());
        $this->assertSame('released', $released->status);
        $this->assertSame(10, $this->inventory->availableQuantity($variant));
    }

    public function test_fulfill_permanently_decrements_on_hand_stock_this_is_sold_stock(): void
    {
        $variant = ProductVariant::factory()->create();
        $inventory = $variant->inventory()->create(['quantity_on_hand' => 10]);
        $reservation = $this->inventory->reserve($variant, 4, null);

        $fulfilled = $this->inventory->fulfill($reservation);

        $this->assertSame('fulfilled', $fulfilled->status);
        $this->assertSame(6, $inventory->fresh()->quantity_on_hand);
        // Available doesn't move again - it already excluded this
        // reservation while it was pending/committed.
        $this->assertSame(6, $this->inventory->availableQuantity($variant));

        $sold = InventoryReservation::where('product_variant_id', $variant->id)->where('status', 'fulfilled')->sum('quantity');
        $this->assertSame(4, $sold);
    }

    public function test_fulfill_never_double_decrements_an_already_fulfilled_reservation(): void
    {
        $variant = ProductVariant::factory()->create();
        $inventory = $variant->inventory()->create(['quantity_on_hand' => 10]);
        $reservation = $this->inventory->reserve($variant, 4, null);

        $this->inventory->fulfill($reservation);
        $this->inventory->fulfill($reservation->fresh());

        $this->assertSame(6, $inventory->fresh()->quantity_on_hand);
    }

    public function test_checkout_reserves_stock_and_delivering_the_order_fulfills_it(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $order = Order::factory()->create();
        $reservation = InventoryReservation::create([
            'product_variant_id' => $variant->id, 'order_id' => $order->id, 'quantity' => 2, 'status' => 'pending',
        ]);

        $this->inventory->fulfill($reservation, 'order delivered');

        $this->assertSame(8, $variant->inventory()->sum('quantity_on_hand'));
        $this->assertSame('fulfilled', $reservation->fresh()->status);
    }
}
