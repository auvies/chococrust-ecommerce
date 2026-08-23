<?php

namespace App\Services\Orders;

use App\Events\OrderCancelled;
use App\Events\OrderConfirmed;
use App\Events\OrderCreated;
use App\Events\OrderDelivered;
use App\Events\OrderDispatched;
use App\Events\OrderReady;
use App\Models\Address;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\DeliveryRule;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderStatusChanged;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Checkout and order-lifecycle logic lives here, not in the controller
 * (CLAUDE.md §2: "business logic lives in services, not route handlers").
 * Pricing is always computed server-side from the current variant price -
 * a client can never supply its own subtotal/total (CLAUDE.md §1: never
 * trust client-side data for business-critical values).
 *
 * Order status is tracked entirely independently of payment status
 * (payments.status) - this service only ever nudges payment status at the
 * two points the fulfillment lifecycle genuinely implies a payment-state
 * change (a COD order being confirmed, or cancelled before payment
 * completed); it never sets a payment to 'paid' itself - only
 * `PaymentService` (staff verification / a future gateway webhook) does
 * that.
 */
class OrderService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /** Fulfillment states an order may still be cancelled from. */
    private const CANCELLABLE_STATUSES = ['pending', 'confirmed', 'processing', 'ready'];

    /**
     * Order Created -> ... -> Completed, matching the business's COD flow
     * brief. 'ready' (prepared, awaiting dispatch) and 'completed' (the
     * order's entire lifecycle - delivery and payment - is settled) are the
     * two additions over the original pending/confirmed/processing/
     * dispatched/delivered graph.
     */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['ready', 'cancelled'],
        'ready' => ['dispatched', 'cancelled'],
        'dispatched' => ['delivered'],
        'delivered' => ['completed', 'refunded'],
        'completed' => ['refunded'],
    ];

    public function create(Customer $customer, array $data): Order
    {
        $order = DB::transaction(function () use ($customer, $data) {
            $lines = $this->priceLines($data['items']);
            $subtotal = collect($lines)->sum('line_total');

            // Loaded (not trusted from the request) and ownership-checked
            // here rather than in the Form Request, since "does this address
            // belong to this customer" needs the authenticated customer,
            // which a route-level `exists:addresses,id` rule can't express.
            $address = Address::query()
                ->where('id', $data['shipping_address_id'])
                ->where('customer_id', $customer->id)
                ->first();

            if (! $address) {
                throw ValidationException::withMessages([
                    'shipping_address_id' => 'This address does not belong to you.',
                ]);
            }

            $delivery = $this->resolveDelivery($data['delivery_type'], $lines, $subtotal, $address);
            $deliveryFee = $delivery['fee'];

            $coupon = null;
            $discount = 0.0;
            if (! empty($data['coupon_code'])) {
                $coupon = $this->validateCoupon($data['coupon_code'], $customer, $subtotal);
                $discount = $this->applyCoupon($coupon, $subtotal, $deliveryFee);
            }

            $total = max($subtotal - $discount + $deliveryFee, 0);

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $customer->id,
                'status' => 'pending',
                'currency' => 'PKR',
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'delivery_fee' => $deliveryFee,
                'tax_total' => 0,
                'total' => $total,
                'delivery_type' => $data['delivery_type'],
                'estimated_delivery_minutes' => $delivery['estimated_minutes'],
                'coupon_id' => $coupon?->id,
                'shipping_address_id' => $address->id,
                // Server-built from the verified address, never the client's
                // own copy — a snapshot exists specifically so the order
                // stays legible even if the address is edited/removed later.
                'shipping_address_snapshot' => $address->only([
                    'label', 'recipient_name', 'phone', 'line1', 'line2',
                    'city', 'area', 'province', 'postal_code', 'country',
                ]),
                'contact_name' => $data['contact_name'],
                'contact_phone' => $data['contact_phone'],
                'contact_email' => $data['contact_email'] ?? null,
                'notes' => $data['notes'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line);
                // Re-validated and locked here (not just the earlier
                // priceLines() pre-check) so two concurrent checkouts for
                // the last unit of stock can't both succeed - see
                // InventoryService's own docblock.
                $this->inventory->reserve(
                    ProductVariant::findOrFail($line['product_variant_id']),
                    $line['quantity'],
                    $order->id,
                );
            }

            if ($coupon) {
                $coupon->increment('times_used');
            }

            $order->statusHistory()->create(['to_status' => 'pending']);

            AuditLogger::log('order.created', $order, after: $order->toArray());

            return $order->load(['items', 'reservations']);
        });

        $this->notifyCustomer($customer, new OrderPlaced($order));
        OrderCreated::dispatch($order->id, $order->order_number, $customer->id, (float) $order->total);

        return $order;
    }

    /**
     * Preview-only: the same eligibility/fee resolution `create()` uses,
     * without placing an order. Lets the checkout UI show "this can't be
     * delivered" (or the fee/ETA) before the customer submits, instead of
     * only finding out after a rejected POST.
     *
     * @return array{fee: float, estimated_minutes: ?int}
     */
    public function previewDelivery(array $items, string $deliveryType, ?Address $address): array
    {
        $lines = $this->priceLines($items);
        $subtotal = collect($lines)->sum('line_total');

        return $this->resolveDelivery($deliveryType, $lines, $subtotal, $address);
    }

    public function transitionStatus(Order $order, string $toStatus, ?string $note, int $actorId): Order
    {
        $allowed = self::TRANSITIONS[$order->status] ?? [];

        if (! in_array($toStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move an order from '{$order->status}' to '{$toStatus}'.",
            ]);
        }

        $fromStatus = $order->status;

        $order = DB::transaction(function () use ($order, $toStatus, $note, $actorId, $fromStatus) {
            $order->update(['status' => $toStatus]);

            match ($toStatus) {
                // A hard hold: still reserved, not yet sold. COD's own
                // payment status moves in lockstep (COD_PENDING ->
                // COD_CONFIRMED) - order status and payment status are
                // still two independent columns, this is just the one
                // point in the flow where confirming the order legitimately
                // implies confirming its payment will proceed.
                'confirmed' => $this->onOrderConfirmed($order),
                // The sale is final: reservations convert to on-hand stock
                // being permanently decremented ("Sold stock").
                'delivered' => $this->onOrderDelivered($order),
                'cancelled' => $this->onOrderCancelled($order),
                default => null,
            };

            $order->statusHistory()->create([
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by' => $actorId,
                'note' => $note,
            ]);

            AuditLogger::log('order.status_changed', $order, before: ['status' => $fromStatus], after: ['status' => $toStatus]);

            return $order;
        });

        $this->notifyCustomer($order->customer, new OrderStatusChanged($order, $fromStatus, $toStatus));
        $this->dispatchLifecycleEvent($order, $toStatus);

        return $order;
    }

    /**
     * Phase 13's event architecture: one named event per order-status
     * transition this service already performs - no new business logic,
     * just making each transition observable to whatever (today: only
     * LogAgentEvent) is listening.
     */
    private function dispatchLifecycleEvent(Order $order, string $toStatus): void
    {
        match ($toStatus) {
            'confirmed' => OrderConfirmed::dispatch($order->id, $order->order_number),
            'ready' => OrderReady::dispatch($order->id, $order->order_number),
            'dispatched' => OrderDispatched::dispatch($order->id, $order->order_number),
            'delivered' => OrderDelivered::dispatch($order->id, $order->order_number),
            'cancelled' => OrderCancelled::dispatch($order->id, $order->order_number),
            default => null,
        };
    }

    /**
     * Order/payment/delivery notifications are a non-critical path
     * (CLAUDE.md §16): a mail/WhatsApp/database-write failure here must
     * never roll back or block the order transition it's attached to, so
     * every dispatch is caught and logged rather than left to bubble up.
     */
    private function notifyCustomer(?Customer $customer, $notification): void
    {
        try {
            $customer?->user?->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('notification.dispatch_failed', [
                'notification' => get_class($notification),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function onOrderConfirmed(Order $order): void
    {
        foreach ($order->reservations as $reservation) {
            $this->inventory->commit($reservation, 'order confirmed');
        }

        $payment = $order->payments()->latest()->first();
        if ($payment && $payment->status === 'cod_pending') {
            $payment->update(['status' => 'cod_confirmed']);
        }
    }

    private function onOrderDelivered(Order $order): void
    {
        foreach ($order->reservations as $reservation) {
            $this->inventory->fulfill($reservation, 'order delivered');
        }
    }

    private function onOrderCancelled(Order $order): void
    {
        foreach ($order->reservations as $reservation) {
            $this->inventory->release($reservation, 'released', 'order cancelled');
        }

        // Never downgrade a payment that has already genuinely settled -
        // only a still-in-flight payment is moot once the order itself is
        // cancelled.
        $payment = $order->payments()->latest()->first();
        if ($payment && in_array($payment->status, ['pending', 'cod_pending', 'cod_confirmed'], true)) {
            $payment->update(['status' => 'cancelled']);
        }
    }

    public function cancel(Order $order, int $actorId, ?string $note = null): Order
    {
        if (! in_array($order->status, self::CANCELLABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'This order can no longer be cancelled.',
            ]);
        }

        return $this->transitionStatus($order, 'cancelled', $note, $actorId);
    }

    /** @return array<int, array{product_variant_id:int, product_id:?int, product_name:string, variant_name:string, sku:string, unit_price:float, quantity:int, customization_note:?string, line_total:float}> */
    private function priceLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $variant = ProductVariant::with('product')->findOrFail($item['product_variant_id']);

            if (! $variant->is_active || ! $variant->product) {
                throw ValidationException::withMessages([
                    'items' => "Variant {$variant->sku} is not currently available.",
                ]);
            }

            if ($this->inventory->availableQuantity($variant) < $item['quantity']) {
                throw ValidationException::withMessages([
                    'items' => "Not enough stock for {$variant->sku}.",
                ]);
            }

            $lines[] = [
                'product_variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'unit_price' => (float) $variant->price,
                'quantity' => $item['quantity'],
                'customization_note' => isset($item['customization_note']) && $item['customization_note'] !== ''
                    ? trim($item['customization_note'])
                    : null,
                'line_total' => (float) $variant->price * $item['quantity'],
            ];
        }

        return $lines;
    }

    /**
     * Deny-by-default (CLAUDE.md §3): a product is only deliverable via the
     * requested delivery type if a configured rule explicitly says so - no
     * matching rule means "not deliverable", not "allow with no fee". Every
     * line item is checked independently so a mixed cart (e.g. a fresh cake
     * plus a nationwide-eligible product) can't slip a not-deliverable item
     * through on the back of a deliverable one; the whole order is rejected
     * if any item fails.
     *
     * Rule specificity, closest wins: a product-scoped rule beats a
     * category-scoped rule (checked from the product's own category up
     * through its ancestors, so a rule on a parent category like "Cakes"
     * also governs its subcategories) beats the global rule. The single
     * most specific rule matched across every line item supplies the
     * order's fee/ETA.
     *
     * @return array{fee: float, estimated_minutes: ?int}
     */
    private function resolveDelivery(string $deliveryType, array $lines, float $subtotal, ?Address $address): array
    {
        $productIds = collect($lines)->pluck('product_id')->filter()->unique()->values();
        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        $bestRule = null;
        $bestSpecificity = -1;

        foreach ($productIds as $productId) {
            $product = $products->get($productId);
            $categoryChain = $this->categoryAndAncestorIds($product?->category_id);

            $rule = $this->mostSpecificRule($deliveryType, (int) $productId, $categoryChain);

            if (! $rule || ! $rule->is_deliverable) {
                throw ValidationException::withMessages([
                    'delivery_type' => "\"{$product?->name}\" cannot be delivered via {$deliveryType} delivery.",
                ]);
            }

            if ($rule->min_order_amount !== null && $subtotal < (float) $rule->min_order_amount) {
                throw ValidationException::withMessages([
                    'delivery_type' => "A minimum order of {$rule->min_order_amount} is required for {$deliveryType} delivery.",
                ]);
            }

            if ($deliveryType === 'local' && ! empty($rule->local_areas)) {
                if (! $address || ! $this->addressInAreas($address, $rule->local_areas)) {
                    throw ValidationException::withMessages([
                        'shipping_address_id' => 'This address is outside our local delivery area.',
                    ]);
                }
            }

            $specificity = match ($rule->scope) {
                'product' => 2,
                'category' => 1,
                default => 0,
            };

            if ($specificity > $bestSpecificity) {
                $bestSpecificity = $specificity;
                $bestRule = $rule;
            }
        }

        return [
            'fee' => $bestRule?->flat_fee !== null ? (float) $bestRule->flat_fee : 0.0,
            'estimated_minutes' => $bestRule?->estimated_minutes,
        ];
    }

    private function mostSpecificRule(string $deliveryType, ?int $productId, array $categoryChain): ?DeliveryRule
    {
        if ($productId && $rule = $this->findRule('product', null, $productId, $deliveryType)) {
            return $rule;
        }

        foreach ($categoryChain as $categoryId) {
            if ($rule = $this->findRule('category', $categoryId, null, $deliveryType)) {
                return $rule;
            }
        }

        return $this->findRule('global', null, null, $deliveryType);
    }

    /** An exact delivery-type match always wins over a same-scope 'both' rule. */
    private function findRule(string $scope, ?int $categoryId, ?int $productId, string $deliveryType): ?DeliveryRule
    {
        $base = DeliveryRule::query()
            ->where('is_active', true)
            ->where('scope', $scope)
            ->where('category_id', $categoryId)
            ->where('product_id', $productId);

        return (clone $base)->where('delivery_type', $deliveryType)->orderByDesc('priority')->first()
            ?? (clone $base)->where('delivery_type', 'both')->orderByDesc('priority')->first();
    }

    /** Self plus every ancestor, closest first, so a category-scoped rule cascades down to its subcategories. */
    private function categoryAndAncestorIds(?int $categoryId): array
    {
        $chain = [];

        while ($categoryId !== null) {
            $chain[] = $categoryId;
            $categoryId = Category::query()->whereKey($categoryId)->value('parent_id');
        }

        return $chain;
    }

    private function addressInAreas(Address $address, array $areas): bool
    {
        $normalized = collect($areas)->map(fn ($area) => strtolower(trim((string) $area)))->filter()->all();
        $city = strtolower(trim((string) $address->city));
        $area = strtolower(trim((string) $address->area));

        return in_array($city, $normalized, true) || ($area !== '' && in_array($area, $normalized, true));
    }

    private function validateCoupon(string $code, Customer $customer, float $subtotal): Coupon
    {
        $coupon = Coupon::where('code', $code)->where('is_active', true)->first();

        if (! $coupon
            || ($coupon->starts_at && $coupon->starts_at->isFuture())
            || ($coupon->expires_at && $coupon->expires_at->isPast())
            || ($coupon->min_order_amount && $subtotal < (float) $coupon->min_order_amount)
            || ($coupon->usage_limit && $coupon->times_used >= $coupon->usage_limit)
        ) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon is not valid.']);
        }

        if ($coupon->usage_limit_per_customer) {
            $used = Order::where('coupon_id', $coupon->id)->where('customer_id', $customer->id)->count();
            if ($used >= $coupon->usage_limit_per_customer) {
                throw ValidationException::withMessages(['coupon_code' => 'You have already used this coupon.']);
            }
        }

        return $coupon;
    }

    private function applyCoupon(Coupon $coupon, float $subtotal, float $deliveryFee): float
    {
        $discount = match ($coupon->type) {
            'percentage' => $subtotal * ((float) $coupon->value / 100),
            'fixed_amount' => (float) $coupon->value,
            'free_delivery' => $deliveryFee,
            default => 0.0,
        };

        if ($coupon->max_discount_amount) {
            $discount = min($discount, (float) $coupon->max_discount_amount);
        }

        return min($discount, $subtotal + $deliveryFee);
    }

    private function generateOrderNumber(): string
    {
        return 'CC-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
