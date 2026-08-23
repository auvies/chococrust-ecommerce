<?php

namespace Tests\Feature\Orders;

use App\Models\Address;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\DeliveryRule;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    /**
     * Delivery is deny-by-default (OrderService::resolveDelivery) — every
     * checkout test needs *some* matching rule to succeed at all. Seeded via
     * `firstOrCreate` on the rule's own unique key so a test that creates
     * its own more specific global 'local' rule (fee, is_deliverable=false,
     * local_areas, ...) *before* calling this is left untouched — this only
     * fills the gap for tests that don't care about delivery-rule specifics.
     */
    private function ensureDefaultLocalDeliveryRule(): void
    {
        DeliveryRule::firstOrCreate(
            ['scope' => 'global', 'category_id' => null, 'product_id' => null, 'delivery_type' => 'local'],
            ['is_deliverable' => true, 'flat_fee' => 0, 'is_active' => true],
        );
    }

    private function checkoutAs($user, ProductVariant $variant, array $overrides = [], array $addressOverrides = []): TestResponse
    {
        $this->ensureDefaultLocalDeliveryRule();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $address = Address::factory()->create(array_merge(['customer_id' => $customer->id], $addressOverrides));

        return $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'test-csrf')
            ->withHeader('X-CSRF-Token', 'test-csrf')
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders', array_merge([
                'items' => [['product_variant_id' => $variant->id, 'quantity' => 2]],
                'delivery_type' => 'local',
                'shipping_address_id' => $address->id,
                'contact_name' => 'Amina Khan',
                'contact_phone' => '03001234567',
                'payment_method' => 'cod',
            ], $overrides));
    }

    public function test_checkout_creates_an_order_with_server_computed_pricing(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);

        $user = $this->makeUserWithRole('customer');

        $response = $this->checkoutAs($user, $variant);

        $response->assertCreated();
        $this->assertEquals(1000.0, $response->json('data.subtotal'));
        $this->assertEquals(1000.0, $response->json('data.total'));
        $response->assertJsonPath('data.status', 'pending');
        $this->assertCount(1, InventoryReservation::all());
    }

    public function test_checkout_rejects_when_stock_is_insufficient(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 1]);

        $user = $this->makeUserWithRole('customer');

        $this->checkoutAs($user, $variant)->assertStatus(422);
    }

    public function test_checkout_applies_a_matching_delivery_rule_fee(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        DeliveryRule::create(['scope' => 'global', 'delivery_type' => 'local', 'is_deliverable' => true, 'flat_fee' => 150]);

        $user = $this->makeUserWithRole('customer');

        $response = $this->checkoutAs($user, $variant);

        $response->assertCreated();
        $this->assertEquals(150.0, $response->json('data.delivery_fee'));
        $this->assertEquals(1150.0, $response->json('data.total'));
    }

    public function test_checkout_rejects_when_the_route_is_not_deliverable(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        DeliveryRule::create(['scope' => 'global', 'delivery_type' => 'local', 'is_deliverable' => false]);

        $user = $this->makeUserWithRole('customer');

        $this->checkoutAs($user, $variant)->assertStatus(422);
    }

    public function test_checkout_applies_a_valid_coupon(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 1000]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        Coupon::create(['code' => 'SAVE10', 'type' => 'percentage', 'value' => 10, 'is_active' => true]);

        $user = $this->makeUserWithRole('customer');

        $response = $this->checkoutAs($user, $variant, [
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
            'coupon_code' => 'SAVE10',
        ]);

        $response->assertCreated();
        $this->assertEquals(100.0, $response->json('data.discount_total'));
        $this->assertEquals(900.0, $response->json('data.total'));
    }

    public function test_checkout_requires_an_idempotency_key_and_replays_on_retry(): void
    {
        $this->ensureDefaultLocalDeliveryRule();
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $address = Address::factory()->create(['customer_id' => $customer->id]);
        $key = (string) Str::uuid();

        $payload = [
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
            'delivery_type' => 'local',
            'shipping_address_id' => $address->id,
            'contact_name' => 'Amina',
            'contact_phone' => '0300',
            'payment_method' => 'cod',
        ];

        $actor = $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'test-csrf')
            ->withHeader('X-CSRF-Token', 'test-csrf')
            ->withHeader('Idempotency-Key', $key);

        $first = $actor->postJson('/api/v1/orders', $payload);
        $first->assertCreated();

        $second = $actor->postJson('/api/v1/orders', $payload);
        $second->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Order::count());
    }

    public function test_checkout_without_an_idempotency_key_is_rejected(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $address = Address::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'test-csrf')
            ->withHeader('X-CSRF-Token', 'test-csrf')
            ->postJson('/api/v1/orders', [
                'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
                'delivery_type' => 'local',
                'shipping_address_id' => $address->id,
                'contact_name' => 'Amina',
                'contact_phone' => '0300',
                'payment_method' => 'cod',
            ])
            ->assertStatus(400);
    }

    public function test_a_customer_cannot_see_another_customers_orders(): void
    {
        $owner = Order::factory()->create();
        $intruder = $this->makeUserWithRole('customer');
        Customer::factory()->create(['user_id' => $intruder->id]);

        $this->actingAs($intruder, 'api')->getJson("/api/v1/orders/{$owner->id}")->assertStatus(403);
    }

    public function test_order_manager_can_transition_status_and_history_is_recorded(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);
        $manager = $this->makeUserWithRole('order_manager');

        $response = $this->actingAs($manager, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'test-csrf')
            ->withHeader('X-CSRF-Token', 'test-csrf')
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'confirmed']);

        $response->assertOk();
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertCount(1, $order->statusHistory);
    }

    public function test_transitioning_order_status_notifies_the_customer(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);
        $manager = $this->makeUserWithRole('order_manager');

        $this->actingAs($manager, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'test-csrf')
            ->withHeader('X-CSRF-Token', 'test-csrf')
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertOk();

        $customerUser = $order->customer->user;
        $this->assertSame(1, $customerUser->notifications()->count());
        $this->assertStringContainsString($order->order_number, $customerUser->notifications()->first()->data['body']);
    }

    public function test_placing_an_order_notifies_the_customer(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');

        $this->checkoutAs($user, $variant)->assertCreated();

        $this->assertSame(1, $user->fresh()->notifications()->count());
    }

    public function test_an_invalid_status_transition_is_rejected(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);
        $manager = $this->makeUserWithRole('order_manager');

        $this->actingAs($manager, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'test-csrf')
            ->withHeader('X-CSRF-Token', 'test-csrf')
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'delivered'])
            ->assertStatus(422);
    }

    public function test_support_cannot_change_order_status(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);
        $support = $this->makeUserWithRole('support');

        $this->actingAs($support, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'test-csrf')
            ->withHeader('X-CSRF-Token', 'test-csrf')
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'confirmed'])
            ->assertStatus(403);
    }

    public function test_cancelling_an_order_releases_its_stock_reservation(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');

        $order = $this->checkoutAs($user, $variant);
        $orderId = $order->json('data.id');

        $response = $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'test-csrf')
            ->withHeader('X-CSRF-Token', 'test-csrf')
            ->postJson("/api/v1/orders/{$orderId}/cancel");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'cancelled');
        $this->assertSame('released', InventoryReservation::first()->status);
    }

    // --- Phase 08: delivery eligibility, customization, and tracking ---

    public function test_checkout_is_rejected_when_no_delivery_rule_covers_the_requested_type(): void
    {
        // Deliberately no DeliveryRule at all — deny-by-default means
        // "nothing configured" must mean "not deliverable", not "free".
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $address = Address::factory()->create(['customer_id' => $customer->id]);

        $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'test-csrf')
            ->withHeader('X-CSRF-Token', 'test-csrf')
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders', [
                'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
                'delivery_type' => 'nationwide',
                'shipping_address_id' => $address->id,
                'contact_name' => 'Amina',
                'contact_phone' => '0300',
                'payment_method' => 'cod',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('delivery_type');

        $this->assertSame(0, Order::count());
    }

    public function test_local_delivery_is_rejected_outside_the_configured_delivery_area(): void
    {
        DeliveryRule::create([
            'scope' => 'global', 'delivery_type' => 'local', 'is_deliverable' => true,
            'flat_fee' => 150, 'local_areas' => ['Khanewal', 'Kabirwala'],
        ]);
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');

        $this->checkoutAs($user, $variant, [], ['city' => 'Lahore'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('shipping_address_id');
    }

    public function test_local_delivery_succeeds_inside_the_configured_delivery_area(): void
    {
        DeliveryRule::create([
            'scope' => 'global', 'delivery_type' => 'local', 'is_deliverable' => true,
            'flat_fee' => 150, 'local_areas' => ['Khanewal', 'Kabirwala'], 'estimated_minutes' => 120,
        ]);
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');

        $response = $this->checkoutAs($user, $variant, [], ['city' => 'Khanewal']);

        $response->assertCreated();
        $this->assertEquals(150.0, $response->json('data.delivery_fee'));
        $response->assertJsonPath('data.estimated_delivery_minutes', 120);
    }

    public function test_local_delivery_matches_a_surrounding_area_by_the_areas_field_too(): void
    {
        DeliveryRule::create([
            'scope' => 'global', 'delivery_type' => 'local', 'is_deliverable' => true,
            'flat_fee' => 150, 'local_areas' => ['Khanewal', 'Kabirwala'],
        ]);
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');

        // City itself isn't in the allow-list, but the neighbourhood/area is.
        $this->checkoutAs($user, $variant, [], ['city' => 'Multan District', 'area' => 'Kabirwala'])
            ->assertCreated();
    }

    public function test_a_fresh_category_product_cannot_be_shipped_nationwide(): void
    {
        DeliveryRule::create(['scope' => 'global', 'delivery_type' => 'nationwide', 'is_deliverable' => true, 'flat_fee' => 300]);
        $cakes = Category::factory()->create(['name' => 'Cakes']);
        DeliveryRule::create(['scope' => 'category', 'category_id' => $cakes->id, 'delivery_type' => 'nationwide', 'is_deliverable' => false]);

        $product = Product::factory()->create(['category_id' => $cakes->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');

        $this->checkoutAs($user, $variant, ['delivery_type' => 'nationwide'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('delivery_type');
    }

    public function test_a_nationwide_eligible_category_is_unaffected_by_another_categorys_block(): void
    {
        DeliveryRule::create(['scope' => 'global', 'delivery_type' => 'nationwide', 'is_deliverable' => true, 'flat_fee' => 300]);
        $cakes = Category::factory()->create(['name' => 'Cakes']);
        DeliveryRule::create(['scope' => 'category', 'category_id' => $cakes->id, 'delivery_type' => 'nationwide', 'is_deliverable' => false]);

        $chocolates = Category::factory()->create(['name' => 'Chocolates']);
        $product = Product::factory()->create(['category_id' => $chocolates->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');

        $response = $this->checkoutAs($user, $variant, ['delivery_type' => 'nationwide']);

        $response->assertCreated();
        $this->assertEquals(300.0, $response->json('data.delivery_fee'));
    }

    public function test_a_delivery_block_on_a_parent_category_cascades_to_its_subcategories(): void
    {
        DeliveryRule::create(['scope' => 'global', 'delivery_type' => 'nationwide', 'is_deliverable' => true, 'flat_fee' => 300]);
        $cakes = Category::factory()->create(['name' => 'Cakes']);
        $freshCream = Category::factory()->create(['name' => 'Fresh Cream Cakes', 'parent_id' => $cakes->id]);
        DeliveryRule::create(['scope' => 'category', 'category_id' => $cakes->id, 'delivery_type' => 'nationwide', 'is_deliverable' => false]);

        // The rule targets the *parent* only — the product sits in the child.
        $product = Product::factory()->create(['category_id' => $freshCream->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');

        $this->checkoutAs($user, $variant, ['delivery_type' => 'nationwide'])->assertStatus(422);
    }

    public function test_minimum_order_amount_is_enforced_for_a_delivery_rule(): void
    {
        DeliveryRule::create([
            'scope' => 'global', 'delivery_type' => 'local', 'is_deliverable' => true,
            'flat_fee' => 150, 'min_order_amount' => 2000,
        ]);
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');

        // 2 x 500 = 1000, below the rule's 2000 minimum.
        $this->checkoutAs($user, $variant)->assertStatus(422);
    }

    public function test_checkout_persists_a_line_items_customization_note(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');

        $response = $this->checkoutAs($user, $variant, [
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 1, 'customization_note' => 'Happy Birthday Ali!']],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.items.0.customization_note', 'Happy Birthday Ali!');
    }

    public function test_checkout_rejects_an_address_that_belongs_to_another_customer(): void
    {
        $this->ensureDefaultLocalDeliveryRule();
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $stranger = Customer::factory()->create();
        $othersAddress = Address::factory()->create(['customer_id' => $stranger->id]);
        $user = $this->makeUserWithRole('customer');
        Customer::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'test-csrf')
            ->withHeader('X-CSRF-Token', 'test-csrf')
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders', [
                'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
                'delivery_type' => 'local',
                'shipping_address_id' => $othersAddress->id,
                'contact_name' => 'Amina',
                'contact_phone' => '0300',
                'payment_method' => 'cod',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('shipping_address_id');

        $this->assertSame(0, Order::count());
    }

    public function test_delivery_eligibility_can_be_previewed_without_placing_an_order(): void
    {
        DeliveryRule::create([
            'scope' => 'global', 'delivery_type' => 'local', 'is_deliverable' => true,
            'flat_fee' => 150, 'local_areas' => ['Khanewal'], 'estimated_minutes' => 120,
        ]);
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        $address = Address::factory()->create(['customer_id' => $customer->id, 'city' => 'Khanewal']);

        $response = $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'test-csrf')
            ->withHeader('X-CSRF-Token', 'test-csrf')
            ->postJson('/api/v1/orders/delivery-eligibility', [
                'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
                'delivery_type' => 'local',
                'shipping_address_id' => $address->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.eligible', true);
        // assertJsonPath uses assertSame - a whole-number float like 150.0
        // is indistinguishable from the int 150 once it's round-tripped
        // through json_encode/decode (PHP drops the trailing .0), so the
        // expectation has to be the int the response actually decodes to.
        $response->assertJsonPath('data.fee', 150);
        $response->assertJsonPath('data.estimated_minutes', 120);
        $this->assertSame(0, Order::count());
    }

    public function test_delivery_eligibility_preview_rejects_an_ineligible_cart(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');
        // A Customer profile is required - the controller looks one up via
        // firstOrFail() before ever reaching eligibility logic, so without
        // this the request 404s before the "ineligible cart" behavior this
        // test targets is even exercised.
        Customer::factory()->create(['user_id' => $user->id]);
        // No delivery rule configured for 'nationwide' at all.

        $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'test-csrf')
            ->withHeader('X-CSRF-Token', 'test-csrf')
            ->postJson('/api/v1/orders/delivery-eligibility', [
                'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
                'delivery_type' => 'nationwide',
            ])
            ->assertStatus(422);
    }

    public function test_customer_can_see_their_orders_delivery_and_tracking_info(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');

        $checkout = $this->checkoutAs($user, $variant);
        $orderId = $checkout->json('data.id');
        $order = Order::findOrFail($orderId);

        $delivery = $order->delivery()->create([
            'type' => 'local', 'status' => 'assigned', 'tracking_number' => 'TRK-1',
        ]);
        $delivery->trackingEvents()->create([
            'status' => 'picked_up', 'location' => 'Khanewal Hub', 'occurred_at' => now(),
        ]);

        $response = $this->actingAs($user, 'api')->getJson("/api/v1/orders/{$orderId}");

        $response->assertOk();
        $response->assertJsonPath('data.delivery.tracking_number', 'TRK-1');
        $response->assertJsonCount(1, 'data.delivery.tracking_events');
        $response->assertJsonPath('data.delivery.tracking_events.0.status', 'picked_up');
    }

    public function test_a_customer_with_no_delivery_yet_sees_a_null_delivery_on_their_order(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');

        $checkout = $this->checkoutAs($user, $variant);
        $orderId = $checkout->json('data.id');

        $response = $this->actingAs($user, 'api')->getJson("/api/v1/orders/{$orderId}");

        $response->assertOk();
        $response->assertJsonPath('data.delivery', null);
    }

    // --- Phase 09: the full COD lifecycle (Order Created -> ... -> Completed), separated from payment status ---

    private function patchOrderStatus($actor, int $orderId, string $status): TestResponse
    {
        return $actor
            ->withUnencryptedCookie('cc_csrf_token', 'test-csrf')
            ->withHeader('X-CSRF-Token', 'test-csrf')
            ->patchJson("/api/v1/orders/{$orderId}/status", ['status' => $status]);
    }

    public function test_the_full_order_lifecycle_reaches_completed(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');
        $manager = $this->makeUserWithRole('order_manager');

        $orderId = $this->checkoutAs($user, $variant)->json('data.id');
        $actor = $this->actingAs($manager, 'api');

        foreach (['confirmed', 'processing', 'ready', 'dispatched', 'delivered', 'completed'] as $status) {
            $this->patchOrderStatus($actor, $orderId, $status)->assertOk()->assertJsonPath('data.status', $status);
        }
    }

    public function test_confirming_a_cod_order_moves_its_payment_from_cod_pending_to_cod_confirmed(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');
        $manager = $this->makeUserWithRole('order_manager');

        $order = $this->checkoutAs($user, $variant)->json('data');
        $this->assertSame('cod_pending', Payment::where('order_id', $order['id'])->value('status'));

        $this->patchOrderStatus($this->actingAs($manager, 'api'), $order['id'], 'confirmed')->assertOk();

        $this->assertSame('cod_confirmed', Payment::where('order_id', $order['id'])->value('status'));
    }

    public function test_confirming_an_order_commits_its_stock_reservations(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');
        $manager = $this->makeUserWithRole('order_manager');

        $orderId = $this->checkoutAs($user, $variant)->json('data.id');
        $this->assertSame('pending', InventoryReservation::first()->status);

        $this->patchOrderStatus($this->actingAs($manager, 'api'), $orderId, 'confirmed')->assertOk();

        $this->assertSame('committed', InventoryReservation::first()->fresh()->status);
    }

    public function test_delivering_an_order_fulfills_its_reservations_and_decrements_on_hand_stock(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $inventory = $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');
        $manager = $this->makeUserWithRole('order_manager');

        // checkoutAs reserves quantity 2 by default.
        $orderId = $this->checkoutAs($user, $variant)->json('data.id');
        $actor = $this->actingAs($manager, 'api');

        foreach (['confirmed', 'processing', 'ready', 'dispatched', 'delivered'] as $status) {
            $this->patchOrderStatus($actor, $orderId, $status)->assertOk();
        }

        $this->assertSame('fulfilled', InventoryReservation::first()->fresh()->status);
        // "Sold stock": on-hand permanently dropped by the sold quantity.
        $this->assertSame(8, $inventory->fresh()->quantity_on_hand);
    }

    public function test_cancelling_a_cod_order_moves_its_still_in_flight_payment_to_cancelled(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');

        $order = $this->checkoutAs($user, $variant)->json('data');

        $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'test-csrf')
            ->withHeader('X-CSRF-Token', 'test-csrf')
            ->postJson("/api/v1/orders/{$order['id']}/cancel")
            ->assertOk();

        $this->assertSame('cancelled', Payment::where('order_id', $order['id'])->value('status'));
    }

    public function test_cancelling_an_order_never_downgrades_an_already_paid_payment(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');
        $manager = $this->makeUserWithRole('manager');

        $order = $this->checkoutAs($user, $variant)->json('data');
        Payment::where('order_id', $order['id'])->update(['status' => 'paid']);

        // 'processing' is still cancellable per CANCELLABLE_STATUSES.
        $this->patchOrderStatus($this->actingAs($manager, 'api'), $order['id'], 'confirmed')->assertOk();

        $this->actingAs($manager, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'test-csrf')
            ->withHeader('X-CSRF-Token', 'test-csrf')
            ->postJson("/api/v1/orders/{$order['id']}/cancel")
            ->assertOk();

        $this->assertSame('paid', Payment::where('order_id', $order['id'])->value('status'));
    }

    public function test_payment_method_accepts_the_configured_gateways(): void
    {
        $this->ensureDefaultLocalDeliveryRule();
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');

        $response = $this->checkoutAs($user, $variant, ['payment_method' => 'easypaisa']);

        $response->assertCreated();
        $payment = Payment::where('order_id', $response->json('data.id'))->first();
        // Not COD - a generic pending status (awaiting a gateway that isn't
        // wired up yet), never COD_PENDING, and never a fabricated "paid".
        $this->assertSame('pending', $payment->status);
        $this->assertSame('easypaisa', $payment->method);
        $this->assertSame('easypaisa', $payment->gateway);
    }

    public function test_payment_method_rejects_a_gateway_not_in_the_configured_list(): void
    {
        $this->ensureDefaultLocalDeliveryRule();
        $variant = ProductVariant::factory()->create(['price' => 500]);
        $variant->inventory()->create(['quantity_on_hand' => 10]);
        $user = $this->makeUserWithRole('customer');

        $this->checkoutAs($user, $variant, ['payment_method' => 'credit_card'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');
    }
}
