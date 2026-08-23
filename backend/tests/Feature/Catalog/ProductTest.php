<?php

namespace Tests\Feature\Catalog;

use App\Models\AuditLog;
use App\Models\HomepageSection;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    /** See CategoryTest's identical helper - `/products` mutations gained `csrf.cookie` this phase too (same pre-existing gap, same fix). */
    private function withCsrf()
    {
        return $this->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x');
    }

    public function test_public_listing_only_shows_active_products(): void
    {
        Product::factory()->create(['status' => 'active']);
        Product::factory()->create(['status' => 'draft']);

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_staff_with_products_view_sees_draft_products_too(): void
    {
        Product::factory()->create(['status' => 'active']);
        Product::factory()->create(['status' => 'draft']);

        $staff = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($staff, 'api')->getJson('/api/v1/products');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_a_draft_product_returns_404_to_the_public(): void
    {
        $product = Product::factory()->create(['status' => 'draft']);

        $this->getJson("/api/v1/products/{$product->id}")->assertStatus(404);
    }

    public function test_creating_a_product_requires_at_least_one_variant(): void
    {
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()->postJson('/api/v1/products', [
            'name' => 'Dark Chocolate Cake',
            'slug' => 'dark-chocolate-cake',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('variants');
    }

    public function test_creating_a_product_with_variants_succeeds_and_is_audit_logged(): void
    {
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()->postJson('/api/v1/products', [
            'name' => 'Dark Chocolate Cake',
            'slug' => 'dark-chocolate-cake',
            'variants' => [
                ['sku' => 'DCC-500G', 'name' => '500g', 'price' => 1200, 'is_default' => true],
                ['sku' => 'DCC-1KG', 'name' => '1kg', 'price' => 2200],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonCount(2, 'data.variants');
    }

    public function test_variant_response_never_exposes_cost_price(): void
    {
        $product = Product::factory()->create();
        $product->variants()->create([
            'sku' => 'X-1', 'name' => 'Default', 'price' => 100, 'cost_price' => 40, 'is_default' => true,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk();
        $response->assertJsonMissing(['cost_price']);
    }

    public function test_only_content_manager_or_higher_can_create_products(): void
    {
        $rider = $this->makeUserWithRole('delivery_rider');

        $this->actingAs($rider, 'api')->withCsrf()->postJson('/api/v1/products', [
            'name' => 'X', 'slug' => 'x', 'variants' => [['sku' => 'X', 'name' => 'X', 'price' => 1]],
        ])->assertStatus(403);
    }

    public function test_a_variant_can_be_added_to_an_existing_product(): void
    {
        $product = Product::factory()->create();
        $product->variants()->create(['sku' => 'X-1', 'name' => 'Default', 'price' => 100, 'is_default' => true]);
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()->postJson("/api/v1/products/{$product->id}/variants", [
            'sku' => 'X-2', 'name' => '1kg', 'price' => 200,
        ]);

        $response->assertCreated();
        $this->assertCount(2, $product->variants()->get());
    }

    public function test_a_variants_price_can_be_updated_and_is_audit_logged(): void
    {
        $product = Product::factory()->create();
        $variant = $product->variants()->create(['sku' => 'X-1', 'name' => 'Default', 'price' => 100, 'is_default' => true]);
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()
            ->putJson("/api/v1/products/{$product->id}/variants/{$variant->id}", ['price' => 150]);

        $response->assertOk();
        $this->assertEquals(150.0, $response->json('data.price'));
        $this->assertNotNull(AuditLog::where('action', 'product_variant.updated')->first());
    }

    public function test_a_products_last_remaining_variant_cannot_be_deleted(): void
    {
        $product = Product::factory()->create();
        $variant = $product->variants()->create(['sku' => 'X-1', 'name' => 'Default', 'price' => 100, 'is_default' => true]);
        $manager = $this->makeUserWithRole('content_manager');

        $this->actingAs($manager, 'api')->withCsrf()
            ->deleteJson("/api/v1/products/{$product->id}/variants/{$variant->id}")
            ->assertStatus(422);
    }

    public function test_managing_variants_requires_products_manage(): void
    {
        $product = Product::factory()->create();
        $variant = $product->variants()->create(['sku' => 'X-1', 'name' => 'Default', 'price' => 100, 'is_default' => true]);
        $support = $this->makeUserWithRole('support');

        $this->actingAs($support, 'api')->withCsrf()
            ->putJson("/api/v1/products/{$product->id}/variants/{$variant->id}", ['price' => 150])
            ->assertStatus(403);
    }

    public function test_products_can_be_filtered_by_a_variant_price_range(): void
    {
        $cheap = Product::factory()->create(['status' => 'active']);
        $cheap->variants()->create(['sku' => 'CHEAP', 'name' => 'Default', 'price' => 300, 'is_default' => true]);

        $mid = Product::factory()->create(['status' => 'active']);
        $mid->variants()->create(['sku' => 'MID', 'name' => 'Default', 'price' => 800, 'is_default' => true]);

        $expensive = Product::factory()->create(['status' => 'active']);
        $expensive->variants()->create(['sku' => 'EXP', 'name' => 'Default', 'price' => 3000, 'is_default' => true]);

        $response = $this->getJson('/api/v1/products?min_price=500&max_price=1000');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $mid->id);
    }

    public function test_best_sellers_are_ranked_by_summed_sales_quantity(): void
    {
        $topSeller = Product::factory()->create(['status' => 'active']);
        $runnerUp = Product::factory()->create(['status' => 'active']);
        $neverSold = Product::factory()->create(['status' => 'active']);

        $orderA = Order::factory()->create(['status' => 'delivered']);
        $orderA->items()->create([
            'product_id' => $topSeller->id, 'product_name' => $topSeller->name,
            'unit_price' => 100, 'quantity' => 10, 'line_total' => 1000,
        ]);

        $orderB = Order::factory()->create(['status' => 'confirmed']);
        $orderB->items()->create([
            'product_id' => $topSeller->id, 'product_name' => $topSeller->name,
            'unit_price' => 100, 'quantity' => 5, 'line_total' => 500,
        ]);
        $orderB->items()->create([
            'product_id' => $runnerUp->id, 'product_name' => $runnerUp->name,
            'unit_price' => 100, 'quantity' => 8, 'line_total' => 800,
        ]);

        $response = $this->getJson('/api/v1/products/best-sellers');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertSame($topSeller->id, $ids->first());
        $this->assertTrue($ids->contains($runnerUp->id));
        $this->assertFalse($ids->contains($neverSold->id));
    }

    public function test_best_sellers_excludes_cancelled_and_refunded_orders(): void
    {
        // A second, genuinely-sold product keeps the response non-empty so
        // this test exercises the exclusion itself, not the "no sales data"
        // fallback (which would otherwise surface $excludedProduct anyway,
        // since it's still a perfectly valid active product).
        $excludedProduct = Product::factory()->create(['status' => 'active']);
        $includedProduct = Product::factory()->create(['status' => 'active']);

        $cancelled = Order::factory()->create(['status' => 'cancelled']);
        $cancelled->items()->create([
            'product_id' => $excludedProduct->id, 'product_name' => $excludedProduct->name,
            'unit_price' => 100, 'quantity' => 50, 'line_total' => 5000,
        ]);

        $refunded = Order::factory()->create(['status' => 'refunded']);
        $refunded->items()->create([
            'product_id' => $excludedProduct->id, 'product_name' => $excludedProduct->name,
            'unit_price' => 100, 'quantity' => 50, 'line_total' => 5000,
        ]);

        $counted = Order::factory()->create(['status' => 'confirmed']);
        $counted->items()->create([
            'product_id' => $includedProduct->id, 'product_name' => $includedProduct->name,
            'unit_price' => 100, 'quantity' => 1, 'line_total' => 100,
        ]);

        $response = $this->getJson('/api/v1/products/best-sellers');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($includedProduct->id));
        $this->assertFalse($ids->contains($excludedProduct->id));
    }

    public function test_best_sellers_can_be_overridden_by_an_admin_configured_homepage_section(): void
    {
        $pinned = Product::factory()->create(['status' => 'active']);
        $organicTopSeller = Product::factory()->create(['status' => 'active']);

        $order = Order::factory()->create(['status' => 'delivered']);
        $order->items()->create([
            'product_id' => $organicTopSeller->id, 'product_name' => $organicTopSeller->name,
            'unit_price' => 100, 'quantity' => 20, 'line_total' => 2000,
        ]);

        HomepageSection::factory()->create([
            'type' => 'best_sellers',
            'config' => ['product_ids' => [$pinned->id]],
        ]);

        $response = $this->getJson('/api/v1/products/best-sellers');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertSame([$pinned->id], $ids->all());
    }

    public function test_best_sellers_falls_back_to_featured_products_with_no_sales_data(): void
    {
        Product::factory()->create(['status' => 'active', 'is_featured' => false]);
        $featured = Product::factory()->create(['status' => 'active', 'is_featured' => true]);

        $response = $this->getJson('/api/v1/products/best-sellers');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $featured->id);
    }
}
