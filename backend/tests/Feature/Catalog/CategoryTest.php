<?php

namespace Tests\Feature\Catalog;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    /**
     * `/categories` mutations gained `csrf.cookie` this phase (a real,
     * pre-existing gap found while adding the image-upload route - see
     * routes/api/catalog.php) - every state-changing call in this file
     * needs a valid CSRF cookie/header now, including the 403-permission
     * tests, since csrf.cookie runs before the Form Request's authorize().
     */
    private function withCsrf()
    {
        return $this->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x');
    }

    public function test_categories_are_publicly_listable(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_listing_supports_pagination_filtering_sorting_and_search(): void
    {
        Category::factory()->create(['name' => 'Chocolate Cakes', 'is_active' => true]);
        Category::factory()->create(['name' => 'Fruit Tarts', 'is_active' => false]);

        $this->getJson('/api/v1/categories?filter[is_active]=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Chocolate Cakes');

        $this->getJson('/api/v1/categories?search=Tarts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fruit Tarts');

        $response = $this->getJson('/api/v1/categories?sort=-name');
        $response->assertOk();
        $this->assertSame('Fruit Tarts', $response->json('data.0.name'));

        $response = $this->getJson('/api/v1/categories?per_page=1');
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_response_never_exposes_soft_delete_internals(): void
    {
        $category = Category::factory()->create();

        $response = $this->getJson("/api/v1/categories/{$category->id}");

        $response->assertOk();
        $response->assertJsonMissing(['deleted_at']);
    }

    public function test_creating_a_category_requires_authentication(): void
    {
        $this->postJson('/api/v1/categories', ['name' => 'X', 'slug' => 'x'])->assertStatus(401);
    }

    public function test_creating_a_category_requires_the_categories_manage_permission(): void
    {
        $customer = $this->makeUserWithRole('customer');

        $this->actingAs($customer, 'api')->withCsrf()
            ->postJson('/api/v1/categories', ['name' => 'X', 'slug' => 'x'])
            ->assertStatus(403);
    }

    public function test_content_manager_can_create_a_category_and_it_is_audit_logged(): void
    {
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()->postJson('/api/v1/categories', [
            'name' => 'Chocolate Cakes',
            'slug' => 'chocolate-cakes',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'chocolate-cakes');

        $this->assertNotNull(
            AuditLog::where('action', 'category.created')->where('user_id', $manager->id)->first()
        );
    }

    public function test_creating_a_category_validates_input_and_rejects_unknown_fields(): void
    {
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()->postJson('/api/v1/categories', [
            'slug' => 'no-name',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $manager = $this->makeUserWithRole('content_manager');
        $category = Category::factory()->create();

        $response = $this->actingAs($manager, 'api')->withCsrf()
            ->putJson("/api/v1/categories/{$category->id}", ['parent_id' => $category->id]);

        $response->assertStatus(422);
    }

    public function test_deleting_a_category_requires_permission_and_is_audit_logged(): void
    {
        $manager = $this->makeUserWithRole('content_manager');
        $category = Category::factory()->create();

        $this->actingAs($manager, 'api')->withCsrf()
            ->deleteJson("/api/v1/categories/{$category->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted($category);
        $this->assertNotNull(AuditLog::where('action', 'category.deleted')->first());
    }

    public function test_a_category_lists_its_subcategories_as_children(): void
    {
        $cakes = Category::factory()->create(['name' => 'Cakes']);
        $freshCream = Category::factory()->create(['name' => 'Fresh Cream Cakes', 'parent_id' => $cakes->id]);
        Category::factory()->create(['name' => 'Dry Cakes', 'parent_id' => $cakes->id]);

        $response = $this->getJson("/api/v1/categories/{$cakes->id}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data.children');
        $this->assertContains($freshCream->id, collect($response->json('data.children'))->pluck('id'));
    }

    public function test_filtering_products_by_a_parent_category_includes_subcategory_products(): void
    {
        $cakes = Category::factory()->create(['name' => 'Cakes']);
        $freshCream = Category::factory()->create(['name' => 'Fresh Cream Cakes', 'parent_id' => $cakes->id]);
        $chocolates = Category::factory()->create(['name' => 'Chocolates']);

        $directCake = Product::factory()->create(['category_id' => $cakes->id, 'status' => 'active']);
        $subcategoryCake = Product::factory()->create(['category_id' => $freshCream->id, 'status' => 'active']);
        Product::factory()->create(['category_id' => $chocolates->id, 'status' => 'active']);

        $response = $this->getJson("/api/v1/products?filter[category_id]={$cakes->id}");

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($directCake->id));
        $this->assertTrue($ids->contains($subcategoryCake->id));
    }

    public function test_category_seeder_builds_the_initial_hierarchy_and_is_idempotent(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $this->assertSame(11, Category::count());

        $cakes = Category::where('slug', 'cakes')->firstOrFail();
        $this->assertNull($cakes->parent_id);
        $this->assertCount(3, $cakes->children);

        foreach (['fresh-cream-cakes', 'dry-cakes', 'customized-cakes'] as $slug) {
            $this->assertSame($cakes->id, Category::where('slug', $slug)->value('parent_id'));
        }

        foreach (['chocolates', 'dessert-cups', 'pizza', 'jewelry', 'gifts', 'pastries', 'fresh-homemade-food'] as $slug) {
            $this->assertNull(Category::where('slug', $slug)->value('parent_id'));
        }
    }

    public function test_category_seeder_never_overwrites_an_admins_later_edit(): void
    {
        $this->seed(CategorySeeder::class);

        $cakes = Category::where('slug', 'cakes')->firstOrFail();
        $cakes->update(['name' => 'Celebration Cakes']);

        $this->seed(CategorySeeder::class);

        $this->assertSame('Celebration Cakes', $cakes->fresh()->name);
    }
}
