<?php

namespace Database\Seeders;

use App\Models\HomepageSection;
use Illuminate\Database\Seeder;

/**
 * Baseline homepage layout (business brief, Phase 07): hero, categories,
 * all products, featured products, best sellers, offers, reviews - in that
 * order, all enabled. Without this, a fresh install has zero homepage_sections
 * rows and the public homepage (which is now section-driven, see
 * frontend `(storefront)/page.tsx`) would render nothing.
 *
 * `firstOrCreate` keyed on `type`: an admin who has already reordered,
 * renamed, or disabled a section through the Homepage Manager must never
 * have that choice reverted by a later seeder run (`homepage_sections.type`
 * has no unique constraint by design - see its migration - so this only
 * seeds a type that doesn't exist yet at all).
 */
class HomepageSectionSeeder extends Seeder
{
    private const SECTIONS = [
        ['type' => 'hero', 'title' => null],
        ['type' => 'categories', 'title' => 'Shop by Category'],
        ['type' => 'all_products', 'title' => 'All Products'],
        ['type' => 'featured_products', 'title' => 'Featured Products'],
        ['type' => 'best_sellers', 'title' => 'Best Sellers'],
        ['type' => 'offers', 'title' => 'Offers'],
        ['type' => 'reviews', 'title' => 'What Customers Say'],
    ];

    public function run(): void
    {
        foreach (self::SECTIONS as $index => $section) {
            HomepageSection::firstOrCreate(
                ['type' => $section['type']],
                ['title' => $section['title'], 'sort_order' => $index, 'is_active' => true],
            );
        }
    }
}
