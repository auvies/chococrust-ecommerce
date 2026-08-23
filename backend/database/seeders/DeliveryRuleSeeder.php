<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DeliveryRule;
use Illuminate\Database\Seeder;

/**
 * Baseline delivery configuration (business brief, Phase 08):
 *
 * - Local delivery is available only in Khanewal City and its surrounding
 *   areas, targeting ~2 hours after order confirmation - encoded as data
 *   (`local_areas`, `estimated_minutes`) on the global 'local' rule, not a
 *   hardcoded check, so support/ops can edit the coverage area or ETA from
 *   the admin panel without a deploy.
 * - Nationwide shipping is available everywhere by default (the global
 *   'nationwide' rule), *except* for the fresh/perishable categories - Cakes
 *   (and its subcategories, via OrderService's ancestor walk), Pastries,
 *   Pizza, Dessert Cups, and Fresh Homemade Food - which get an explicit
 *   category-scoped block. Pakistan-wide product lines (Chocolates, Jewelry,
 *   and future garments/toys/handmade goods) need no rule of their own:
 *   they simply inherit the global nationwide rule, which is also why a
 *   brand new category is nationwide-eligible by default with zero code
 *   changes, matching Phase 07's "future categories" contract.
 *
 * `firstOrCreate` keyed on the table's own unique constraint
 * (scope/category_id/product_id/delivery_type) - safe to run in every
 * environment, and never overwrites a fee/area/ETA an admin has since
 * edited through the Category Manager's delivery rules section.
 */
class DeliveryRuleSeeder extends Seeder
{
    private const FRESH_CATEGORY_SLUGS = ['cakes', 'pastries', 'pizza', 'dessert-cups', 'fresh-homemade-food'];

    public function run(): void
    {
        DeliveryRule::firstOrCreate(
            ['scope' => 'global', 'category_id' => null, 'product_id' => null, 'delivery_type' => 'local'],
            [
                'is_deliverable' => true,
                'flat_fee' => 150,
                'local_areas' => ['Khanewal', 'Kabirwala', 'Mian Channu', 'Jahanian', 'Tulamba'],
                'estimated_minutes' => 120,
                'priority' => 0,
                'is_active' => true,
            ],
        );

        DeliveryRule::firstOrCreate(
            ['scope' => 'global', 'category_id' => null, 'product_id' => null, 'delivery_type' => 'nationwide'],
            [
                'is_deliverable' => true,
                'flat_fee' => 300,
                'priority' => 0,
                'is_active' => true,
            ],
        );

        foreach (self::FRESH_CATEGORY_SLUGS as $slug) {
            $category = Category::where('slug', $slug)->first();

            if (! $category) {
                continue; // CategorySeeder hasn't seeded it (or it was renamed/removed) - nothing to scope a rule to.
            }

            DeliveryRule::firstOrCreate(
                ['scope' => 'category', 'category_id' => $category->id, 'product_id' => null, 'delivery_type' => 'nationwide'],
                ['is_deliverable' => false, 'priority' => 10, 'is_active' => true],
            );
        }
    }
}
