<?php

namespace Tests\Feature\Catalog;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\DeliveryRule;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DeliveryRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class DeliveryRuleTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    private function asCsrf($actor)
    {
        return $actor->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x');
    }

    public function test_listing_requires_authentication(): void
    {
        $this->getJson('/api/v1/delivery-rules')->assertStatus(401);
    }

    public function test_listing_requires_the_categories_manage_permission(): void
    {
        $support = $this->makeUserWithRole('support');

        $this->actingAs($support, 'api')
            ->getJson('/api/v1/delivery-rules')
            ->assertStatus(403);
    }

    public function test_content_manager_can_list_delivery_rules(): void
    {
        DeliveryRule::factory()->count(2)->create();
        $manager = $this->makeUserWithRole('content_manager');

        $this->actingAs($manager, 'api')
            ->getJson('/api/v1/delivery-rules')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_a_global_rule_can_be_created_and_is_audit_logged(): void
    {
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->asCsrf($this->actingAs($manager, 'api'))->postJson('/api/v1/delivery-rules', [
            'scope' => 'global',
            'delivery_type' => 'local',
            'is_deliverable' => true,
            'flat_fee' => 150,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.scope', 'global');
        $this->assertNotNull(AuditLog::where('action', 'delivery_rule.created')->first());
    }

    public function test_a_category_scoped_rule_requires_a_category_id(): void
    {
        $manager = $this->makeUserWithRole('content_manager');

        $this->asCsrf($this->actingAs($manager, 'api'))->postJson('/api/v1/delivery-rules', [
            'scope' => 'category',
            'delivery_type' => 'local',
        ])->assertStatus(422)->assertJsonValidationErrors('scope');
    }

    public function test_a_category_scoped_rule_can_be_created_against_a_real_category(): void
    {
        $category = Category::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->asCsrf($this->actingAs($manager, 'api'))->postJson('/api/v1/delivery-rules', [
            'scope' => 'category',
            'category_id' => $category->id,
            'delivery_type' => 'nationwide',
            'is_deliverable' => false,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.category_id', $category->id);
    }

    public function test_creating_a_delivery_rule_requires_permission(): void
    {
        $customer = $this->makeUserWithRole('customer');

        $this->asCsrf($this->actingAs($customer, 'api'))
            ->postJson('/api/v1/delivery-rules', ['scope' => 'global', 'delivery_type' => 'both'])
            ->assertStatus(403);
    }

    public function test_updating_a_rule_is_audit_logged(): void
    {
        $rule = DeliveryRule::factory()->create(['flat_fee' => 100]);
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->asCsrf($this->actingAs($manager, 'api'))
            ->putJson("/api/v1/delivery-rules/{$rule->id}", ['flat_fee' => 200]);

        $response->assertOk();
        $this->assertEquals(200.0, $response->json('data.flat_fee'));
        $this->assertNotNull(AuditLog::where('action', 'delivery_rule.updated')->first());
    }

    public function test_switching_scope_without_the_matching_id_is_rejected(): void
    {
        $rule = DeliveryRule::factory()->create(['scope' => 'global']);
        $manager = $this->makeUserWithRole('content_manager');

        $this->asCsrf($this->actingAs($manager, 'api'))
            ->putJson("/api/v1/delivery-rules/{$rule->id}", ['scope' => 'category'])
            ->assertStatus(422);
    }

    public function test_deleting_a_rule_soft_deletes_it_and_is_audit_logged(): void
    {
        $rule = DeliveryRule::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        $this->asCsrf($this->actingAs($manager, 'api'))
            ->deleteJson("/api/v1/delivery-rules/{$rule->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted($rule);
        $this->assertNotNull(AuditLog::where('action', 'delivery_rule.deleted')->first());
    }

    // --- Phase 08: local delivery area / ETA configuration ---

    public function test_a_rule_can_be_created_with_local_areas_and_an_estimated_eta(): void
    {
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->asCsrf($this->actingAs($manager, 'api'))->postJson('/api/v1/delivery-rules', [
            'scope' => 'global',
            'delivery_type' => 'local',
            'is_deliverable' => true,
            'flat_fee' => 150,
            'local_areas' => ['Khanewal', 'Kabirwala'],
            'estimated_minutes' => 120,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.local_areas', ['Khanewal', 'Kabirwala']);
        $response->assertJsonPath('data.estimated_minutes', 120);
    }

    public function test_a_rules_local_areas_can_be_edited(): void
    {
        $rule = DeliveryRule::factory()->create(['delivery_type' => 'local', 'local_areas' => ['Khanewal']]);
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->asCsrf($this->actingAs($manager, 'api'))
            ->putJson("/api/v1/delivery-rules/{$rule->id}", ['local_areas' => ['Khanewal', 'Kabirwala', 'Jahanian']]);

        $response->assertOk();
        $response->assertJsonPath('data.local_areas', ['Khanewal', 'Kabirwala', 'Jahanian']);
    }

    public function test_delivery_rule_seeder_configures_the_khanewal_local_zone_and_fresh_category_blocks(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(DeliveryRuleSeeder::class);
        $this->seed(DeliveryRuleSeeder::class);

        $local = DeliveryRule::where('scope', 'global')->where('delivery_type', 'local')->firstOrFail();
        $this->assertTrue($local->is_deliverable);
        $this->assertContains('Khanewal', $local->local_areas);
        $this->assertNotNull($local->estimated_minutes);

        $nationwide = DeliveryRule::where('scope', 'global')->where('delivery_type', 'nationwide')->firstOrFail();
        $this->assertTrue($nationwide->is_deliverable);

        foreach (['cakes', 'pastries', 'pizza', 'dessert-cups', 'fresh-homemade-food'] as $slug) {
            $category = Category::where('slug', $slug)->firstOrFail();
            $block = DeliveryRule::where('scope', 'category')
                ->where('category_id', $category->id)
                ->where('delivery_type', 'nationwide')
                ->first();

            $this->assertNotNull($block, "Expected a nationwide block for '{$slug}'.");
            $this->assertFalse($block->is_deliverable);
        }

        // Chocolates/Jewelry (Pakistan-wide lines) get no rule of their own —
        // they simply inherit the global nationwide rule.
        foreach (['chocolates', 'jewelry'] as $slug) {
            $category = Category::where('slug', $slug)->firstOrFail();
            $this->assertNull(
                DeliveryRule::where('scope', 'category')->where('category_id', $category->id)->first(),
            );
        }
    }

    public function test_delivery_rule_seeder_never_overwrites_an_admins_later_edit(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(DeliveryRuleSeeder::class);

        $local = DeliveryRule::where('scope', 'global')->where('delivery_type', 'local')->firstOrFail();
        $local->update(['flat_fee' => 999]);

        $this->seed(DeliveryRuleSeeder::class);

        $this->assertEquals(999.0, $local->fresh()->flat_fee);
    }
}
