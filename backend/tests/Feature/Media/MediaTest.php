<?php

namespace Tests\Feature\Media;

use App\Models\Category;
use App\Models\HeroBanner;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->withCredentials();
        Storage::fake('local');
    }

    private function withCsrf()
    {
        return $this->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x');
    }

    public function test_content_manager_can_upload_product_media(): void
    {
        $product = Product::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->post('/api/v1/media', [
                'product_id' => $product->id,
                'file' => UploadedFile::fake()->image('cake.jpg'),
            ]);

        $response->assertCreated();
        Storage::disk('local')->assertExists('media/'.basename(parse_url($response->json('data.url'), PHP_URL_PATH)));
    }

    public function test_a_non_image_file_is_rejected(): void
    {
        $product = Product::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->post('/api/v1/media', [
                'product_id' => $product->id,
                'file' => UploadedFile::fake()->create('malware.php', 10),
            ]);

        $response->assertStatus(422);
    }

    /**
     * Security regression test (SECURITY_AUDIT.md): a file whose *content*
     * is a genuine, validation-passing image but whose *original filename*
     * carries an executable-shaped extension must never be stored with
     * that extension - MediaUploadService derives the stored extension
     * from the validated content (server-side MIME sniffing), never from
     * the client-supplied original filename. Uses ".pht" rather than
     * ".php": Laravel's own `mimes`/`image` validation rule already
     * hardcodes a block on the literal php/php3/php4/php5/php7/php8/
     * phtml/phar extensions (`shouldBlockPhpUpload()`), so a `.php`-named
     * upload never reaches this far regardless of this fix - `.pht` isn't
     * in that hardcoded list and reaches MediaUploadService::store(),
     * which is exactly the residual gap this fix closes.
     */
    public function test_a_genuine_images_stored_extension_is_never_taken_from_an_executable_shaped_original_filename(): void
    {
        $product = Product::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        // A genuinely valid fake JPEG (the same generator every other test
        // in this file already relies on passing validation), re-wrapped
        // with a dangerous-shaped original name - isolates exactly the bug
        // this regression test targets (client-name-derived extension)
        // without also depending on this environment's own GD encoding
        // behavior.
        $genuineImage = UploadedFile::fake()->image('shell.jpg');
        $file = new UploadedFile($genuineImage->getRealPath(), 'shell.pht', 'image/jpeg', null, true);

        $response = $this->actingAs($manager, 'api')->withCsrf()->post('/api/v1/media', [
            'product_id' => $product->id,
            'file' => $file,
        ]);

        $response->assertCreated();
        $url = $response->json('data.url');
        $this->assertStringNotContainsString('.php', $url);
        $this->assertMatchesRegularExpression('/\.(jpe?g|png|webp)$/i', $url);
    }

    public function test_a_customer_cannot_upload_media(): void
    {
        $product = Product::factory()->create();
        $user = $this->makeUserWithRole('customer');

        $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->post('/api/v1/media', [
                'product_id' => $product->id,
                'file' => UploadedFile::fake()->image('cake.jpg'),
            ])
            ->assertStatus(403);
    }

    public function test_uploaded_filenames_are_never_the_client_supplied_name(): void
    {
        $product = Product::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->post('/api/v1/media', [
                'product_id' => $product->id,
                'file' => UploadedFile::fake()->image('../../etc/passwd.jpg'),
            ]);

        $response->assertCreated();
        $this->assertStringNotContainsString('passwd', $response->json('data.url'));
    }

    public function test_deleting_media_requires_permission(): void
    {
        $media = ProductMedia::factory()->create();
        $user = $this->makeUserWithRole('customer');

        $this->actingAs($user, 'api')
            ->withUnencryptedCookie('cc_csrf_token', 'x')->withHeader('X-CSRF-Token', 'x')
            ->deleteJson("/api/v1/media/{$media->id}")
            ->assertStatus(403);
    }

    // --- Category images ---

    public function test_content_manager_can_upload_a_category_image(): void
    {
        $category = Category::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()
            ->post("/api/v1/categories/{$category->id}/image", [
                'file' => UploadedFile::fake()->image('cakes.jpg'),
                'alt_text' => 'A tray of fresh cream cakes',
            ]);

        $response->assertOk();
        $this->assertSame('A tray of fresh cream cakes', $category->fresh()->alt_text);
        Storage::disk('local')->assertExists('categories/'.basename(parse_url($response->json('data.image_url'), PHP_URL_PATH)));
    }

    public function test_category_image_upload_rejects_a_non_image_file(): void
    {
        $category = Category::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        $this->actingAs($manager, 'api')->withCsrf()
            ->post("/api/v1/categories/{$category->id}/image", ['file' => UploadedFile::fake()->create('shell.php', 10)])
            ->assertStatus(422);
    }

    public function test_category_image_upload_rejects_an_oversized_file(): void
    {
        $category = Category::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        $this->actingAs($manager, 'api')->withCsrf()
            ->post("/api/v1/categories/{$category->id}/image", ['file' => UploadedFile::fake()->image('huge.jpg')->size(6000)])
            ->assertStatus(422);
    }

    public function test_category_image_upload_requires_categories_manage(): void
    {
        $category = Category::factory()->create();
        $support = $this->makeUserWithRole('support');

        $this->actingAs($support, 'api')->withCsrf()
            ->post("/api/v1/categories/{$category->id}/image", ['file' => UploadedFile::fake()->image('x.jpg')])
            ->assertStatus(403);
    }

    public function test_category_image_upload_never_trusts_the_client_filename(): void
    {
        $category = Category::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()
            ->post("/api/v1/categories/{$category->id}/image", ['file' => UploadedFile::fake()->image('../../etc/passwd.jpg')]);

        $response->assertOk();
        $this->assertStringNotContainsString('passwd', $response->json('data.image_url'));
    }

    public function test_category_mutations_are_rejected_without_a_valid_csrf_token(): void
    {
        $category = Category::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        // Authenticated and permitted, but no CSRF cookie/header at all.
        $this->actingAs($manager, 'api')
            ->post("/api/v1/categories/{$category->id}/image", ['file' => UploadedFile::fake()->image('x.jpg')])
            ->assertStatus(419);
    }

    // --- Hero banner images ---

    public function test_content_manager_can_upload_a_hero_banners_desktop_and_mobile_images(): void
    {
        $banner = HeroBanner::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()
            ->post("/api/v1/hero-banners/{$banner->id}/image", [
                'desktop_image' => UploadedFile::fake()->image('desktop.jpg', 1600, 600),
                'mobile_image' => UploadedFile::fake()->image('mobile.jpg', 600, 800),
                'alt_text' => 'Chocolate cake sale banner',
            ]);

        $response->assertOk();
        $this->assertNotNull($response->json('data.image_url'));
        $this->assertNotNull($response->json('data.mobile_image_url'));
        $this->assertSame('Chocolate cake sale banner', $response->json('data.alt_text'));
        Storage::disk('local')->assertExists('hero-banners/'.basename(parse_url($response->json('data.image_url'), PHP_URL_PATH)));
    }

    public function test_hero_banner_image_upload_accepts_just_a_mobile_replacement(): void
    {
        $banner = HeroBanner::factory()->create(['image_url' => 'https://example.com/desktop.jpg']);
        $manager = $this->makeUserWithRole('content_manager');

        $response = $this->actingAs($manager, 'api')->withCsrf()
            ->post("/api/v1/hero-banners/{$banner->id}/image", ['mobile_image' => UploadedFile::fake()->image('mobile.jpg')]);

        $response->assertOk();
        $this->assertSame('https://example.com/desktop.jpg', $response->json('data.image_url'));
        $this->assertNotNull($response->json('data.mobile_image_url'));
    }

    public function test_hero_banner_image_upload_requires_at_least_one_file(): void
    {
        $banner = HeroBanner::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        $this->actingAs($manager, 'api')->withCsrf()
            ->post("/api/v1/hero-banners/{$banner->id}/image", ['alt_text' => 'no files here'])
            ->assertStatus(422);
    }

    public function test_hero_banner_image_upload_rejects_a_non_image_file(): void
    {
        $banner = HeroBanner::factory()->create();
        $manager = $this->makeUserWithRole('content_manager');

        $this->actingAs($manager, 'api')->withCsrf()
            ->post("/api/v1/hero-banners/{$banner->id}/image", ['desktop_image' => UploadedFile::fake()->create('malware.exe', 10)])
            ->assertStatus(422);
    }

    public function test_hero_banner_image_upload_requires_content_manage(): void
    {
        $banner = HeroBanner::factory()->create();
        $rider = $this->makeUserWithRole('delivery_rider');

        $this->actingAs($rider, 'api')->withCsrf()
            ->post("/api/v1/hero-banners/{$banner->id}/image", ['desktop_image' => UploadedFile::fake()->image('x.jpg')])
            ->assertStatus(403);
    }
}
