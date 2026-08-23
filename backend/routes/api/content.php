<?php

use App\Http\Controllers\Api\V1\Content\HeroBannerController;
use App\Http\Controllers\Api\V1\Content\HomepageSectionController;
use App\Http\Controllers\Api\V1\Content\SeoMetadataController;
use App\Http\Controllers\Api\V1\Content\ThemeController;
use Illuminate\Support\Facades\Route;

// Storefront content is public read (CLAUDE.md §3), content.manage-gated
// writes - handled inside each controller so the same GET works for both
// the public site and the admin preview (staff see drafts/inactive too).
Route::get('/themes', [ThemeController::class, 'index']);
Route::get('/themes/active', [ThemeController::class, 'active']);
// Bug fix (QA pass): this was previously placed in the auth-required group
// below, contradicting this comment's own stated design ("the same GET
// works for both the public site and the admin preview") and inconsistent
// with `index()` just above, which already returns every theme's full
// config - including inactive/draft ones - with no auth at all. Gating
// only `show()` added no real protection (the same data was already fully
// public via `index()`) while silently breaking the admin panel's
// unauthenticated preview-by-id use case the docblock on
// ThemeController::show() describes.
Route::get('/themes/{theme}', [ThemeController::class, 'show']);
Route::get('/homepage-sections', [HomepageSectionController::class, 'index']);
Route::get('/hero-banners', [HeroBannerController::class, 'index']);
Route::get('/hero-banners/{heroBanner}', [HeroBannerController::class, 'show']);
Route::get('/seo/{type}/{id}', [SeoMetadataController::class, 'show']);

Route::middleware(['auth:api', 'human', 'csrf.cookie'])->group(function (): void {
    Route::post('/themes', [ThemeController::class, 'store']);
    Route::put('/themes/{theme}', [ThemeController::class, 'update']);
    Route::post('/themes/{theme}/activate', [ThemeController::class, 'activate']);
    Route::post('/themes/{theme}/deactivate', [ThemeController::class, 'deactivate']);

    Route::post('/homepage-sections', [HomepageSectionController::class, 'store']);
    Route::put('/homepage-sections/{homepageSection}', [HomepageSectionController::class, 'update']);
    Route::delete('/homepage-sections/{homepageSection}', [HomepageSectionController::class, 'destroy']);

    Route::post('/hero-banners', [HeroBannerController::class, 'store']);
    Route::put('/hero-banners/{heroBanner}', [HeroBannerController::class, 'update']);
    // Registered before the {heroBanner} wildcard routes below so "reorder"
    // is never swallowed by route-model binding (same reasoning as
    // /products/best-sellers - see routes/api/catalog.php).
    Route::patch('/hero-banners/reorder', [HeroBannerController::class, 'reorder']);
    Route::post('/hero-banners/{heroBanner}/image', [HeroBannerController::class, 'uploadImage']);
    Route::post('/hero-banners/{heroBanner}/restore', [HeroBannerController::class, 'restore']);
    Route::delete('/hero-banners/{heroBanner}', [HeroBannerController::class, 'destroy']);

    Route::put('/seo/{type}/{id}', [SeoMetadataController::class, 'upsert']);
});
