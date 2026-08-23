<?php

namespace Tests\Feature\Database;

use App\Models\Address;
use App\Models\Category;
use App\Models\CodRecord;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\DeliveryRule;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Exercises every migration end to end (via RefreshDatabase) and asserts
 * the architectural requirements from Phase 02: hierarchical categories,
 * future-proof product/category tagging, category/product-scoped delivery
 * rules, the order/payment/COD status separation, soft deletes, and the
 * uniqueness constraints the schema relies on.
 */
class SchemaArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_TABLES = [
        'users', 'roles', 'permissions', 'permission_role', 'role_user',
        'customers', 'addresses', 'customer_notes', 'customer_tags', 'customer_tag_assignments',
        'categories', 'products', 'category_product',
        'product_variants', 'product_media', 'inventory', 'inventory_reservations',
        'inventory_movements', 'delivery_rules', 'coupons', 'coupon_scopes', 'orders', 'order_items',
        'order_status_histories', 'payments', 'payment_transactions', 'cod_records',
        'deliveries', 'delivery_tracking_events', 'reviews', 'hero_banners',
        'themes', 'homepage_sections', 'seo_metadata', 'notifications',
        'chat_conversations', 'chat_messages', 'ai_usage_logs', 'audit_logs',
        'system_settings', 'notification_templates',
        'agent_pending_actions', 'agent_event_logs',
    ];

    public function test_all_expected_tables_exist(): void
    {
        foreach (self::EXPECTED_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_categories_support_parent_child_hierarchy(): void
    {
        $parent = Category::create(['name' => 'Cakes', 'slug' => 'cakes']);
        $child = Category::create(['name' => 'Chocolate Cakes', 'slug' => 'chocolate-cakes', 'parent_id' => $parent->id]);

        $this->assertTrue($parent->children->contains($child));
        $this->assertSame($parent->id, $child->parent->id);

        // Deleting the parent promotes the child to top-level rather than
        // cascading the whole subtree away.
        $parent->forceDelete();
        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_products_belong_to_categories_in_a_future_proof_way(): void
    {
        $canonical = Category::create(['name' => 'Cakes', 'slug' => 'cakes']);
        $secondary = Category::create(['name' => 'Gift Sets', 'slug' => 'gift-sets']);

        $product = Product::create([
            'category_id' => $canonical->id,
            'name' => 'Dark Chocolate Fudge Cake',
            'slug' => 'dark-chocolate-fudge-cake',
            'status' => 'active',
        ]);

        // Future-proof: a product can be tagged into additional categories
        // beyond its single canonical one, via the pivot.
        $product->categories()->attach([$canonical->id, $secondary->id]);

        $this->assertSame($canonical->id, $product->category->id);
        $this->assertCount(2, $product->categories);
        $this->assertTrue($product->categories->pluck('id')->contains($secondary->id));
    }

    public function test_delivery_rules_are_configurable_by_category_and_by_product(): void
    {
        $category = Category::create(['name' => 'Cakes', 'slug' => 'cakes']);
        $product = Product::create(['name' => 'Fudge Cake', 'slug' => 'fudge-cake', 'status' => 'active']);

        $categoryRule = DeliveryRule::create([
            'scope' => 'category',
            'category_id' => $category->id,
            'delivery_type' => 'local',
            'is_deliverable' => true,
        ]);

        $productRule = DeliveryRule::create([
            'scope' => 'product',
            'product_id' => $product->id,
            'delivery_type' => 'nationwide',
            'is_deliverable' => false,
            'priority' => 10,
        ]);

        $this->assertSame($category->id, $categoryRule->category->id);
        $this->assertSame($product->id, $productRule->product->id);
        $this->assertTrue($category->deliveryRules->contains($categoryRule));
        $this->assertTrue($product->deliveryRules->contains($productRule));
    }

    public function test_delivery_rule_scope_must_match_its_target_column(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // scope=global but a category_id is set - structurally inconsistent.
        DeliveryRule::create([
            'scope' => 'global',
            'category_id' => Category::create(['name' => 'Cakes', 'slug' => 'cakes'])->id,
            'delivery_type' => 'local',
        ]);
    }

    public function test_order_status_and_payment_status_are_tracked_independently(): void
    {
        $order = $this->makeOrder();

        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => 'cod',
            'status' => 'pending',
            'amount' => $order->total,
        ]);

        // Advancing fulfillment doesn't touch payment status, and vice
        // versa - they are separate columns on separate tables.
        $order->update(['status' => 'dispatched']);
        $this->assertSame('dispatched', $order->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->status);

        $payment->update(['status' => 'paid']);
        $this->assertSame('dispatched', $order->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_cod_has_its_own_controlled_state_separate_from_payment_status(): void
    {
        $order = $this->makeOrder();
        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => 'cod',
            'status' => 'pending',
            'amount' => $order->total,
        ]);

        $cod = CodRecord::create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'status' => 'awaiting_delivery',
            'amount_due' => $order->total,
        ]);

        $cod->update(['status' => 'collected', 'amount_collected' => $order->total]);

        // The COD-specific state machine moved; the generic payment status
        // did not change automatically - they are deliberately decoupled.
        $this->assertSame('collected', $cod->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_soft_deletes_are_enabled_on_key_entities(): void
    {
        $category = Category::create(['name' => 'Cakes', 'slug' => 'cakes']);
        $order = $this->makeOrder();

        $category->delete();
        $order->delete();

        $this->assertSoftDeleted($category);
        $this->assertSoftDeleted($order);

        // Soft-deleted rows are excluded by default but still recoverable.
        $this->assertNull(Category::find($category->id));
        $this->assertNotNull(Category::withTrashed()->find($category->id));
    }

    public function test_unique_constraints_are_enforced(): void
    {
        Category::create(['name' => 'Cakes', 'slug' => 'cakes']);

        $this->expectException(QueryException::class);
        Category::create(['name' => 'Cakes Duplicate', 'slug' => 'cakes']);
    }

    public function test_coupon_code_uniqueness_is_enforced(): void
    {
        Coupon::create(['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10]);

        $this->expectException(QueryException::class);
        Coupon::create(['code' => 'WELCOME10', 'type' => 'fixed_amount', 'value' => 100]);
    }

    public function test_review_is_unique_per_product_and_customer(): void
    {
        $customer = $this->makeCustomer();
        $product = Product::create(['name' => 'Fudge Cake', 'slug' => 'fudge-cake', 'status' => 'active']);

        Review::create(['product_id' => $product->id, 'customer_id' => $customer->id, 'rating' => 5]);

        $this->expectException(QueryException::class);
        Review::create(['product_id' => $product->id, 'customer_id' => $customer->id, 'rating' => 3]);
    }

    public function test_order_item_snapshot_survives_product_deletion(): void
    {
        $order = $this->makeOrder();
        $variant = $this->makeVariant();

        $item = $order->items()->create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'product_name' => $variant->product->name,
            'sku' => $variant->sku,
            'unit_price' => $variant->price,
            'quantity' => 2,
            'line_total' => $variant->price * 2,
        ]);

        $variant->product->forceDelete();

        // The line item keeps its name/sku snapshot even though the
        // product it pointed to is gone.
        $item->refresh();
        $this->assertNull($item->product_id);
        $this->assertSame('Fudge Cake', $item->product_name);
    }

    private function makeCustomer(): Customer
    {
        $user = User::create([
            'name' => 'Test Customer',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);

        return Customer::create(['user_id' => $user->id]);
    }

    private function makeVariant(): ProductVariant
    {
        $product = Product::create(['name' => 'Fudge Cake', 'slug' => 'fudge-cake', 'status' => 'active']);

        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'FUDGE-500G',
            'name' => '500g',
            'price' => 1200,
            'is_default' => true,
        ]);
    }

    private function makeOrder(): Order
    {
        $customer = $this->makeCustomer();
        $address = Address::create([
            'customer_id' => $customer->id,
            'recipient_name' => 'Test Customer',
            'phone' => '03001234567',
            'line1' => 'Street 1',
            'city' => 'Karachi',
        ]);

        return Order::create([
            'order_number' => 'CC-'.fake()->unique()->numerify('######'),
            'customer_id' => $customer->id,
            'status' => 'pending',
            'subtotal' => 1200,
            'total' => 1200,
            'delivery_type' => 'local',
            'shipping_address_id' => $address->id,
            'contact_name' => 'Test Customer',
            'contact_phone' => '03001234567',
        ]);
    }
}
