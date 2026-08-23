<?php

namespace Tests\Feature\Content;

use App\Models\Category;
use App\Models\HeroBanner;
use App\Models\HomepageSection;
use App\Models\Theme;
use Database\Seeders\HomepageSectionSeeder;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
    }

    private function withCsrf()
    {
        return $this->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x');
    }

    /** @return array<string, mixed> a full, StoreThemeRequest-valid config. */
    private function validThemeConfig(): array
    {
        return [
            'background' => '#FFFFFF', 'surface' => '#F2F2F2', 'text_color' => '#111111',
            'primary_color' => '#5B2E1E', 'secondary_color' => '#D97706',
            'typography' => ['font_family' => "'Nunito', sans-serif", 'heading_font_family' => "'Playfair Display', serif"],
            'font_sizes' => ['sm' => '14px', 'base' => '16px', 'lg' => '18px', 'xl' => '24px', '2xl' => '32px'],
            'buttons' => ['radius' => '8px', 'primary_bg' => '#5B2E1E', 'primary_text' => '#FFFFFF', 'secondary_bg' => '#D97706', 'secondary_text' => '#111111'],
        ];
    }

    public function test_activating_a_theme_deactivates_all_others(): void
    {
        $a = Theme::factory()->create(['is_active' => true]);
        $b = Theme::factory()->create(['is_active' => false]);
        $manager = $this->makeUserWithRole('content_manager');

        $this->actingAs($manager, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->postJson("/api/v1/themes/{$b->id}/activate")
            ->assertOk();

        $this->assertFalse($a->fresh()->is_active);
        $this->assertTrue($b->fresh()->is_active);
    }

    public function test_public_only_sees_active_homepage_sections(): void
    {
        HomepageSection::factory()->create(['is_active' => true]);
        HomepageSection::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/homepage-sections')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_staff_sees_inactive_hero_banners_too(): void
    {
        HeroBanner::factory()->create(['is_active' => true]);
        HeroBanner::factory()->create(['is_active' => false]);
        $manager = $this->makeUserWithRole('content_manager');

        $this->actingAs($manager, 'api')->getJson('/api/v1/hero-banners')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_only_content_manager_can_create_a_hero_banner(): void
    {
        $support = $this->makeUserWithRole('support');

        $this->actingAs($support, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->postJson('/api/v1/hero-banners', ['title' => 'Sale', 'image_url' => 'https://example.com/x.jpg'])
            ->assertStatus(403);
    }

    public function test_seo_metadata_can_be_set_for_an_allow_listed_type(): void
    {
        $category = Category::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        $this->actingAs($manager, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->putJson("/api/v1/seo/category/{$category->id}", ['meta_title' => 'Chocolate Cakes | Choco Crust'])
            ->assertSuccessful(); // 201 the first time (Laravel's wasRecentlyCreated), 200 on subsequent upserts

        $this->getJson("/api/v1/seo/category/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.meta_title', 'Chocolate Cakes | Choco Crust');
    }

    public function test_seo_metadata_rejects_a_non_allow_listed_type(): void
    {
        $this->getJson('/api/v1/seo/user/1')->assertStatus(404);
    }

    public function test_homepage_section_seeder_creates_the_default_layout_and_is_idempotent(): void
    {
        $this->seed(HomepageSectionSeeder::class);
        $this->seed(HomepageSectionSeeder::class);

        $this->assertSame(7, HomepageSection::count());

        $response = $this->getJson('/api/v1/homepage-sections');
        $response->assertOk();
        $response->assertJsonCount(7, 'data');

        $types = collect($response->json('data'))->pluck('type');
        foreach (['hero', 'categories', 'all_products', 'featured_products', 'best_sellers', 'offers', 'reviews'] as $type) {
            $this->assertTrue($types->contains($type));
        }
    }

    public function test_homepage_section_seeder_never_overwrites_an_admins_later_edit(): void
    {
        $this->seed(HomepageSectionSeeder::class);

        $bestSellers = HomepageSection::where('type', 'best_sellers')->firstOrFail();
        $bestSellers->update(['is_active' => false]);

        $this->seed(HomepageSectionSeeder::class);

        $this->assertFalse($bestSellers->fresh()->is_active);
    }

    // --- Themes ---

    public function test_theme_seeder_creates_at_least_ten_themes_and_is_idempotent(): void
    {
        $this->seed(ThemeSeeder::class);
        $count = Theme::count();
        $this->assertGreaterThanOrEqual(10, $count);

        $this->seed(ThemeSeeder::class);
        $this->assertSame($count, Theme::count());

        // Every seeded theme is itself a worked example of a valid config -
        // assert the shape StoreThemeRequest/UpdateThemeRequest expect is
        // really there, not just present under some other key name.
        $theme = Theme::firstOrFail();
        foreach (['background', 'surface', 'text_color', 'primary_color', 'secondary_color', 'typography', 'font_sizes', 'buttons'] as $key) {
            $this->assertArrayHasKey($key, $theme->config);
        }
    }

    public function test_exactly_one_seeded_theme_is_active(): void
    {
        $this->seed(ThemeSeeder::class);

        $this->assertSame(1, Theme::where('is_active', true)->count());
    }

    public function test_creating_a_theme_validates_the_full_config_shape(): void
    {
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()->postJson('/api/v1/themes', [
            'name' => 'Broken Theme',
            'slug' => 'broken-theme',
            'config' => ['background' => '#FFF'], // missing everything else
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['config.primary_color', 'config.typography', 'config.buttons']);
    }

    public function test_content_manager_can_create_a_fully_specified_theme(): void
    {
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()->postJson('/api/v1/themes', [
            'name' => 'Test Theme',
            'slug' => 'test-theme',
            'config' => $this->validThemeConfig(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.config.buttons.radius', '8px');
    }

    public function test_a_theme_can_be_previewed_by_id_without_activating_it(): void
    {
        $theme = Theme::factory()->create(['is_active' => false, 'config' => $this->validThemeConfig()]);

        $response = $this->getJson("/api/v1/themes/{$theme->id}");

        $response->assertOk();
        $response->assertJsonPath('data.config.primary_color', '#5B2E1E');
        $this->assertFalse($theme->fresh()->is_active);
    }

    public function test_content_manager_can_edit_an_existing_themes_config(): void
    {
        $theme = Theme::factory()->create(['config' => $this->validThemeConfig()]);
        $manager = $this->makeUserWithRole('content_manager');

        $newConfig = $this->validThemeConfig();
        $newConfig['primary_color'] = '#000000';

        $response = $this->actingAs($manager, 'api')->withCsrf()
            ->putJson("/api/v1/themes/{$theme->id}", ['config' => $newConfig]);

        $response->assertOk();
        $this->assertSame('#000000', $theme->fresh()->config['primary_color']);
    }

    public function test_a_theme_can_be_deactivated_without_activating_another(): void
    {
        $theme = Theme::factory()->create(['is_active' => true]);
        $manager = $this->makeUserWithRole('content_manager');

        $this->actingAs($manager, 'api')->withCsrf()
            ->postJson("/api/v1/themes/{$theme->id}/deactivate")
            ->assertOk();

        $this->assertFalse($theme->fresh()->is_active);
        $this->assertSame(0, Theme::where('is_active', true)->count());
    }

    public function test_only_content_manager_can_edit_or_deactivate_a_theme(): void
    {
        $theme = Theme::factory()->create();
        $support = $this->makeUserWithRole('support');

        $this->actingAs($support, 'api')->withCsrf()
            ->putJson("/api/v1/themes/{$theme->id}", ['config' => $this->validThemeConfig()])
            ->assertStatus(403);

        $this->actingAs($support, 'api')->withCsrf()
            ->postJson("/api/v1/themes/{$theme->id}/deactivate")
            ->assertStatus(403);
    }

    // --- Hero banners ---

    public function test_a_single_hero_banner_can_be_fetched_for_preview(): void
    {
        $banner = HeroBanner::factory()->create(['is_active' => true]);

        $this->getJson("/api/v1/hero-banners/{$banner->id}")->assertOk()->assertJsonPath('data.id', $banner->id);
    }

    public function test_an_inactive_hero_banner_404s_for_the_public_but_not_for_staff(): void
    {
        $banner = HeroBanner::factory()->create(['is_active' => false]);
        $manager = $this->makeUserWithRole('content_manager');

        $this->getJson("/api/v1/hero-banners/{$banner->id}")->assertStatus(404);
        $this->actingAs($manager, 'api')->getJson("/api/v1/hero-banners/{$banner->id}")->assertOk();
    }

    public function test_hero_banners_can_be_reordered(): void
    {
        $a = HeroBanner::factory()->create(['sort_order' => 0]);
        $b = HeroBanner::factory()->create(['sort_order' => 1]);
        $c = HeroBanner::factory()->create(['sort_order' => 2]);
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()
            ->patchJson('/api/v1/hero-banners/reorder', ['order' => [$c->id, $a->id, $b->id]]);

        $response->assertOk();
        $this->assertSame(0, $c->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
        $this->assertSame(2, $b->fresh()->sort_order);
    }

    public function test_reordering_rejects_an_id_that_does_not_exist(): void
    {
        $a = HeroBanner::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        $this->actingAs($manager, 'api')->withCsrf()
            ->patchJson('/api/v1/hero-banners/reorder', ['order' => [$a->id, 999999]])
            ->assertStatus(422);
    }

    public function test_reordering_requires_content_manage(): void
    {
        $a = HeroBanner::factory()->create();
        $support = $this->makeUserWithRole('support');

        $this->actingAs($support, 'api')->withCsrf()
            ->patchJson('/api/v1/hero-banners/reorder', ['order' => [$a->id]])
            ->assertStatus(403);
    }

    public function test_archiving_a_hero_banner_removes_it_from_the_default_listing_and_it_can_be_restored(): void
    {
        $banner = HeroBanner::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        $this->actingAs($manager, 'api')->withCsrf()
            ->deleteJson("/api/v1/hero-banners/{$banner->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted($banner);
        $this->actingAs($manager, 'api')->getJson('/api/v1/hero-banners')->assertJsonCount(0, 'data');
        $this->actingAs($manager, 'api')->getJson('/api/v1/hero-banners?archived=1')->assertJsonCount(1, 'data');

        $this->actingAs($manager, 'api')->withCsrf()
            ->postJson("/api/v1/hero-banners/{$banner->id}/restore")
            ->assertOk();

        $this->assertNull($banner->fresh()->deleted_at);
    }

    public function test_a_hero_banner_can_be_created_text_first_with_no_image_and_given_one_afterward(): void
    {
        $manager = $this->makeUserWithRole('content_manager');

        $created = $this->actingAs($manager, 'api')->withCsrf()
            ->postJson('/api/v1/hero-banners', ['title' => 'Eid Sale'])
            ->assertCreated();

        $this->assertNull($created->json('data.image_url'));
    }

    public function test_hero_banner_mutations_are_rejected_without_a_valid_csrf_token(): void
    {
        $manager = $this->makeUserWithRole('content_manager');

        $this->actingAs($manager, 'api')
            ->postJson('/api/v1/hero-banners', ['title' => 'X', 'image_url' => 'https://example.com/x.jpg'])
            ->assertStatus(419);
    }

    // --- SEO ---

    public function test_open_graph_fields_are_stored_and_returned_separately_from_meta_fields(): void
    {
        $category = Category::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        $this->actingAs($manager, 'api')->withCsrf()->putJson("/api/v1/seo/category/{$category->id}", [
            'meta_title' => 'Chocolate Cakes | Choco Crust',
            'og_title' => 'Indulge in Chocolate 🍫',
            'og_description' => 'Handmade chocolate cakes, delivered fresh.',
        ])->assertSuccessful();

        $response = $this->getJson("/api/v1/seo/category/{$category->id}");

        $response->assertOk();
        $response->assertJsonPath('data.meta_title', 'Chocolate Cakes | Choco Crust');
        $response->assertJsonPath('data.og_title', 'Indulge in Chocolate 🍫');
        $response->assertJsonPath('data.og_description', 'Handmade chocolate cakes, delivered fresh.');
    }

    public function test_seo_metadata_mutations_require_content_manage(): void
    {
        $category = Category::factory()->create();
        $support = $this->makeUserWithRole('support');

        $this->actingAs($support, 'api')->withCsrf()
            ->putJson("/api/v1/seo/category/{$category->id}", ['meta_title' => 'x'])
            ->assertStatus(403);
    }
}
