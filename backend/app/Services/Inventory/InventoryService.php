<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single place stock is ever read or changed. "Available" is always
 * computed (on-hand minus active reservations), never a separately-stored
 * number that could drift - this was already the Phase 02 design; what this
 * service adds is the missing back half of the lifecycle: a reservation
 * actually becoming a sale ("Sold stock"), and every movement landing in a
 * queryable history instead of only the generic audit log.
 *
 * Every mutating method locks the rows it reads with `lockForUpdate()`
 * inside a transaction, so two concurrent checkouts racing for the last
 * unit of stock can't both succeed - the second transaction blocks until
 * the first commits, then re-reads a figure that already accounts for it.
 * (SQLite has no real row-level locking - `lockForUpdate()` is a no-op
 * there - but the guard is correct and load-bearing on Postgres/MySQL in
 * production; the tests exercise the check-then-act logic itself, which is
 * identical either way.)
 */
class InventoryService
{
    private const ACTIVE_STATUSES = ['pending', 'committed'];

    public function availableQuantity(ProductVariant $variant): int
    {
        $onHand = (int) Inventory::where('product_variant_id', $variant->id)->sum('quantity_on_hand');
        $reserved = (int) InventoryReservation::where('product_variant_id', $variant->id)
            ->whereIn('status', self::ACTIVE_STATUSES)->sum('quantity');

        return $onHand - $reserved;
    }

    /** Places a new hold on stock. Throws if not enough is available. */
    public function reserve(ProductVariant $variant, int $quantity, ?int $orderId, ?int $minutesUntilExpiry = 30): InventoryReservation
    {
        return DB::transaction(function () use ($variant, $quantity, $orderId, $minutesUntilExpiry) {
            $onHand = (int) Inventory::where('product_variant_id', $variant->id)->lockForUpdate()->sum('quantity_on_hand');
            $reserved = (int) InventoryReservation::where('product_variant_id', $variant->id)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->sum('quantity');

            if ($onHand - $reserved < $quantity) {
                throw ValidationException::withMessages(['items' => "Not enough stock for {$variant->sku}."]);
            }

            $reservation = InventoryReservation::create([
                'product_variant_id' => $variant->id,
                'order_id' => $orderId,
                'quantity' => $quantity,
                'status' => 'pending',
                'expires_at' => $minutesUntilExpiry ? now()->addMinutes($minutesUntilExpiry) : null,
            ]);

            $this->recordMovement($variant->id, 'reservation_created', -$quantity, $reservation->id, $orderId, null);

            return $reservation;
        });
    }

    /** A soft hold becomes a hard one (e.g. the order was confirmed) - still reserved, not yet sold. */
    public function commit(InventoryReservation $reservation, ?string $reason = null): InventoryReservation
    {
        return DB::transaction(function () use ($reservation, $reason) {
            $locked = InventoryReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                return $locked; // already committed/released/fulfilled - nothing to do, not an error
            }

            $locked->update(['status' => 'committed']);
            $this->recordMovement($locked->product_variant_id, 'reservation_committed', 0, $locked->id, $locked->order_id, $reason);

            return $locked->fresh();
        });
    }

    /**
     * Releases a hold back to available stock (order cancelled, reservation
     * expired). Idempotent by design: releasing an already-released/
     * fulfilled reservation is a no-op, not an error - two concurrent
     * callers (a cancel request and an expiry sweep) might both try.
     */
    public function release(InventoryReservation $reservation, string $status = 'released', ?string $reason = null): InventoryReservation
    {
        return DB::transaction(function () use ($reservation, $status, $reason) {
            $locked = InventoryReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, self::ACTIVE_STATUSES, true)) {
                return $locked;
            }

            $locked->update(['status' => $status]);
            $this->recordMovement($locked->product_variant_id, "reservation_{$status}", $locked->quantity, $locked->id, $locked->order_id, $reason);

            return $locked->fresh();
        });
    }

    /**
     * A reservation converts into an actual sale: on-hand stock is
     * permanently decremented by the reserved quantity. "Available" doesn't
     * move (it already excluded this reservation as pending/committed) but
     * "on hand" drops and "sold" (the sum of every fulfilled reservation)
     * rises - this is what "Sold stock" means as a queryable figure.
     */
    public function fulfill(InventoryReservation $reservation, ?string $reason = null): InventoryReservation
    {
        return DB::transaction(function () use ($reservation, $reason) {
            $locked = InventoryReservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, self::ACTIVE_STATUSES, true)) {
                return $locked; // already fulfilled/released - never double-decrement stock
            }

            $inventory = Inventory::where('product_variant_id', $locked->product_variant_id)
                ->lockForUpdate()
                ->orderByDesc('quantity_on_hand')
                ->first();

            if ($inventory) {
                $inventory->update(['quantity_on_hand' => max($inventory->quantity_on_hand - $locked->quantity, 0)]);
            }

            $locked->update(['status' => 'fulfilled']);
            $this->recordMovement($locked->product_variant_id, 'sale_fulfilled', -$locked->quantity, $locked->id, $locked->order_id, $reason);

            return $locked->fresh();
        });
    }

    /** Manual stock correction (restock, damage, recount) - always a delta with a reason, never a bare "set to X" (CLAUDE.md §15). */
    public function adjust(Inventory $inventory, int $delta, string $reason, ?int $actorId = null): Inventory
    {
        return DB::transaction(function () use ($inventory, $delta, $reason, $actorId) {
            $locked = Inventory::whereKey($inventory->id)->lockForUpdate()->firstOrFail();
            $newQuantity = $locked->quantity_on_hand + $delta;

            if ($newQuantity < 0) {
                throw ValidationException::withMessages(['delta' => 'This adjustment would take stock below zero.']);
            }

            $locked->update(['quantity_on_hand' => $newQuantity]);
            $this->recordMovement($locked->product_variant_id, 'manual_adjustment', $delta, null, null, $reason, $actorId);

            return $locked->fresh();
        });
    }

    private function recordMovement(
        int $variantId,
        string $type,
        int $quantityDelta,
        ?int $reservationId,
        ?int $orderId,
        ?string $reason,
        ?int $actorId = null,
    ): void {
        InventoryMovement::create([
            'product_variant_id' => $variantId,
            'type' => $type,
            'quantity_delta' => $quantityDelta,
            'inventory_reservation_id' => $reservationId,
            'order_id' => $orderId,
            'reason' => $reason,
            'created_by' => $actorId,
        ]);
    }
}
