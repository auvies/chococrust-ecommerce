<?php

namespace Tests\Feature\Reviews;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    public function test_public_listing_only_shows_approved_reviews(): void
    {
        $product = Product::factory()->create();
        Review::factory()->create(['product_id' => $product->id, 'is_approved' => true]);
        Review::factory()->create(['product_id' => $product->id, 'is_approved' => false]);

        $response = $this->getJson("/api/v1/products/{$product->id}/reviews");

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_customer_can_leave_a_review(): void
    {
        $product = Product::factory()->create();
        $user = $this->makeUserWithRole('customer');
        Customer::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->postJson("/api/v1/products/{$product->id}/reviews", ['rating' => 5, 'body' => 'Loved it']);

        $response->assertCreated();
        $response->assertJsonPath('data.is_approved', false);
    }

    public function test_a_customer_cannot_review_the_same_product_twice(): void
    {
        $product = Product::factory()->create();
        $user = $this->makeUserWithRole('customer');
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        Review::factory()->create(['product_id' => $product->id, 'customer_id' => $customer->id]);

        $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->postJson("/api/v1/products/{$product->id}/reviews", ['rating' => 4])
            ->assertStatus(422);
    }

    public function test_content_manager_can_approve_a_review(): void
    {
        $review = Review::factory()->create(['is_approved' => false]);
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->patchJson("/api/v1/reviews/{$review->id}/approve");

        $response->assertOk();
        $this->assertTrue($review->fresh()->is_approved);
    }

    public function test_a_customer_cannot_approve_reviews(): void
    {
        $review = Review::factory()->create();
        $user = $this->makeUserWithRole('customer');

        $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->patchJson("/api/v1/reviews/{$review->id}/approve")
            ->assertStatus(403);
    }

    public function test_the_global_moderation_queue_requires_reviews_moderate(): void
    {
        $support = $this->makeUserWithRole('support');

        $this->actingAs($support, 'api')->getJson('/api/v1/reviews')->assertStatus(403);
    }

    public function test_the_global_moderation_queue_spans_every_product_and_can_filter_by_approval(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        Review::factory()->create(['product_id' => $productA->id, 'is_approved' => false]);
        Review::factory()->create(['product_id' => $productB->id, 'is_approved' => true]);
        $manager = $this->makeUserWithRole('content_manager');

        $this->actingAs($manager, 'api')
            ->getJson('/api/v1/reviews?filter[is_approved]=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_id', $productA->id);
    }

    public function test_product_detail_exposes_the_true_average_rating_across_every_approved_review(): void
    {
        $product = Product::factory()->create(['status' => 'active']);
        Review::factory()->create(['product_id' => $product->id, 'rating' => 5, 'is_approved' => true]);
        Review::factory()->create(['product_id' => $product->id, 'rating' => 3, 'is_approved' => true]);
        // Not approved - must not pull the average down or count toward rating_count.
        Review::factory()->create(['product_id' => $product->id, 'rating' => 1, 'is_approved' => false]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk();
        $this->assertEquals(4.0, $response->json('data.rating_average'));
        $this->assertSame(2, $response->json('data.rating_count'));
    }

    public function test_a_product_with_no_approved_reviews_has_a_null_average_not_a_zero(): void
    {
        $product = Product::factory()->create(['status' => 'active']);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk();
        $this->assertNull($response->json('data.rating_average'));
        $this->assertSame(0, $response->json('data.rating_count'));
    }
}
