<?php

namespace Tests\Feature\Analytics;

use App\Models\CodRecord;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class AnalyticsDashboardTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    private function getAnalytics(string $path)
    {
        $manager = $this->makeUserWithRole('manager');

        return $this->actingAs($manager, 'api')->getJson($path);
    }

    // --- Centralized permission gate covers every route in the group ---

    public function test_every_analytics_route_requires_analytics_view_permission(): void
    {
        $support = $this->makeUserWithRole('support');
        $routes = [
            '/analytics/overview', '/analytics/sales', '/analytics/orders', '/analytics/products',
            '/analytics/best-sellers', '/analytics/customers', '/analytics/cod', '/analytics/deliveries',
            '/analytics/inventory', '/analytics/payments', '/analytics/ai-usage',
        ];

        foreach ($routes as $route) {
            $this->actingAs($support, 'api')->getJson("/api/v1{$route}")->assertStatus(403);
        }
    }

    // --- Order analytics ---

    public function test_order_analytics_reports_status_mix_average_value_and_cancellation_rate(): void
    {
        Order::factory()->create(['status' => 'pending', 'total' => 100]);
        Order::factory()->create(['status' => 'confirmed', 'total' => 200]);
        Order::factory()->create(['status' => 'cancelled', 'total' => 300]);

        $response = $this->getAnalytics('/api/v1/analytics/orders');

        $response->assertOk();
        $this->assertSame(3, $response->json('data.orders_total'));
        $this->assertEquals(200.0, $response->json('data.average_order_value'));
        $this->assertSame(1, $response->json('data.cancelled_orders'));
        $this->assertEqualsWithDelta(0.3333, $response->json('data.cancellation_rate'), 0.001);
    }

    // --- Product performance / best sellers ---

    public function test_product_performance_and_best_sellers_rank_differently_by_revenue_vs_quantity(): void
    {
        $cheapButPopular = Product::factory()->create(['name' => 'Cheap But Popular']);
        $expensiveButRare = Product::factory()->create(['name' => 'Expensive But Rare']);

        $order = Order::factory()->create(['status' => 'confirmed']);
        $order->items()->create([
            'product_id' => $cheapButPopular->id, 'product_name' => $cheapButPopular->name,
            'unit_price' => 10, 'quantity' => 10, 'line_total' => 100,
        ]);
        $order->items()->create([
            'product_id' => $expensiveButRare->id, 'product_name' => $expensiveButRare->name,
            'unit_price' => 250, 'quantity' => 2, 'line_total' => 500,
        ]);

        // A cancelled order's items must never count toward either ranking.
        $cancelledOrder = Order::factory()->create(['status' => 'cancelled']);
        $cancelledOrder->items()->create([
            'product_id' => $cheapButPopular->id, 'product_name' => $cheapButPopular->name,
            'unit_price' => 10, 'quantity' => 999, 'line_total' => 9990,
        ]);

        $bestSellers = $this->getAnalytics('/api/v1/analytics/best-sellers')->assertOk()->json('data');
        $this->assertSame('Cheap But Popular', $bestSellers[0]['name']);
        $this->assertSame(10, $bestSellers[0]['quantity_sold']);

        $performance = $this->getAnalytics('/api/v1/analytics/products')->assertOk()->json('data');
        $this->assertSame('Expensive But Rare', $performance[0]['name']);
        $this->assertEquals(500.0, $performance[0]['revenue']);
    }

    // --- Customer metrics ---

    public function test_customer_metrics_reports_repeat_rate_and_top_spenders(): void
    {
        $repeatCustomer = Customer::factory()->create();
        Order::factory()->count(2)->create(['customer_id' => $repeatCustomer->id, 'total' => 150]);

        $oneTimeCustomer = Customer::factory()->create();
        Order::factory()->create(['customer_id' => $oneTimeCustomer->id, 'total' => 100]);

        $response = $this->getAnalytics('/api/v1/analytics/customers');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.customers_with_orders'));
        $this->assertSame(1, $response->json('data.repeat_customers'));
        $this->assertEqualsWithDelta(0.5, $response->json('data.repeat_customer_rate'), 0.001);
        $this->assertSame($repeatCustomer->id, $response->json('data.top_customers.0.customer_id'));
        $this->assertEquals(300.0, $response->json('data.top_customers.0.total_spent'));
    }

    // --- COD success rate ---

    public function test_cod_success_rate_excludes_still_pending_records(): void
    {
        CodRecord::factory()->count(2)->create(['status' => 'collected']);
        CodRecord::factory()->create(['status' => 'deposited']);
        CodRecord::factory()->create(['status' => 'failed_collection']);
        CodRecord::factory()->create(['status' => 'awaiting_delivery']);

        $response = $this->getAnalytics('/api/v1/analytics/cod');

        $response->assertOk();
        $this->assertSame(3, $response->json('data.collected'));
        $this->assertSame(1, $response->json('data.failed'));
        $this->assertSame(1, $response->json('data.awaiting_delivery'));
        // 3 collected / 4 resolved (pending excluded from the denominator).
        $this->assertEqualsWithDelta(0.75, $response->json('data.success_rate'), 0.001);
    }

    public function test_cod_success_rate_is_null_with_nothing_resolved_yet(): void
    {
        CodRecord::factory()->create(['status' => 'awaiting_delivery']);

        $response = $this->getAnalytics('/api/v1/analytics/cod');

        $response->assertOk();
        $this->assertNull($response->json('data.success_rate'));
    }

    // --- Failed delivery rate ---

    public function test_failed_delivery_rate_excludes_still_in_flight_deliveries(): void
    {
        Delivery::factory()->count(3)->create(['status' => 'delivered', 'delivery_attempts' => 1]);
        Delivery::factory()->create(['status' => 'failed', 'delivery_attempts' => 3]);
        Delivery::factory()->create(['status' => 'returned', 'delivery_attempts' => 2]);
        Delivery::factory()->create(['status' => 'out_for_delivery', 'delivery_attempts' => 1]);

        $response = $this->getAnalytics('/api/v1/analytics/deliveries');

        $response->assertOk();
        // (1 failed + 1 returned) / 5 resolved (delivered+failed+returned).
        $this->assertEqualsWithDelta(0.4, $response->json('data.failed_delivery_rate'), 0.001);
        $this->assertSame(6, $response->json('data.total_deliveries'));
    }

    // --- Inventory analytics ---

    public function test_inventory_analytics_flags_low_and_out_of_stock_accounting_for_active_reservations(): void
    {
        $healthy = ProductVariant::factory()->create();
        Inventory::factory()->create(['product_variant_id' => $healthy->id, 'quantity_on_hand' => 10, 'reorder_level' => 5]);

        $outOfStock = ProductVariant::factory()->create();
        Inventory::factory()->create(['product_variant_id' => $outOfStock->id, 'quantity_on_hand' => 0, 'reorder_level' => null]);

        // On-hand looks healthy, but an active reservation eats into what's actually available.
        $reservedLow = ProductVariant::factory()->create();
        Inventory::factory()->create(['product_variant_id' => $reservedLow->id, 'quantity_on_hand' => 10, 'reorder_level' => 8]);
        InventoryReservation::factory()->create(['product_variant_id' => $reservedLow->id, 'quantity' => 5, 'status' => 'committed']);
        // A released reservation must not count against availability.
        InventoryReservation::factory()->create(['product_variant_id' => $reservedLow->id, 'quantity' => 100, 'status' => 'released']);

        $response = $this->getAnalytics('/api/v1/analytics/inventory');

        $response->assertOk();
        $this->assertSame(1, $response->json('data.out_of_stock_count'));
        $this->assertSame(1, $response->json('data.low_stock_count'));
        $this->assertSame(20, $response->json('data.total_units_on_hand'));
        $this->assertSame(15, $response->json('data.total_units_available'));
    }

    // --- Payment analytics ---

    public function test_payment_analytics_reports_failure_and_refund_rates_by_method(): void
    {
        Payment::factory()->count(2)->create(['method' => 'cod', 'status' => 'paid', 'amount' => 100]);
        Payment::factory()->create(['method' => 'easypaisa', 'status' => 'failed', 'amount' => 300]);
        Payment::factory()->create(['method' => 'jazzcash', 'status' => 'refunded', 'amount' => 150]);

        $response = $this->getAnalytics('/api/v1/analytics/payments');

        $response->assertOk();
        $this->assertSame(4, $response->json('data.total_payments'));
        $this->assertEquals(200.0, $response->json('data.total_paid_amount'));
        $this->assertEqualsWithDelta(0.25, $response->json('data.failure_rate'), 0.001);
        $this->assertEqualsWithDelta(0.25, $response->json('data.refund_rate'), 0.001);
    }
}
