<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Baseline category tree: Cakes as a parent with three subcategories, plus
 * top-level categories spanning both the original business brief (Phase 07)
 * and the fresh/nationwide product lines added in Phase 08 - Pastries and
 * Fresh Homemade Food join the fresh-goods side, alongside Pizza and Dessert
 * Cups. Categories are fully admin-manageable after this (CategoryController,
 * categories.manage) - this seeder only guarantees the storefront isn't
 * empty on a fresh install, and `DeliveryRuleSeeder` looks these fresh
 * categories up by slug to seed their "no nationwide shipping" rule.
 *
 * `firstOrCreate` keyed on slug, not `updateOrCreate`: once a category
 * exists, a later seeder run must never stomp an admin's rename/description
 * edit made through the admin panel. This only fills gaps, same contract as
 * every other seeder that runs safely in production - which is also why
 * growing this list (Pastries/Fresh Homemade Food, added in Phase 08) is
 * always safe: it never touches a category seeded by an earlier run.
 *
 * Future categories (Handmade Garments, Kids Garments, Toys, ...) are
 * deliberately NOT hardcoded here - CLAUDE.md's "no speculative code" rule
 * and the business brief's "addable without code changes" requirement both
 * point the same way: they get added via the admin panel when the business
 * is ready, not pre-seeded.
 */
class CategorySeeder extends Seeder
{
    private const SUBCATEGORIES_OF_CAKES = [
        ['name' => 'Fresh Cream Cakes', 'slug' => 'fresh-cream-cakes'],
        ['name' => 'Dry Cakes', 'slug' => 'dry-cakes'],
        ['name' => 'Customized Cakes', 'slug' => 'customized-cakes'],
    ];

    private const TOP_LEVEL = [
        ['name' => 'Chocolates', 'slug' => 'chocolates'],
        ['name' => 'Dessert Cups', 'slug' => 'dessert-cups'],
        ['name' => 'Pizza', 'slug' => 'pizza'],
        ['name' => 'Jewelry', 'slug' => 'jewelry'],
        ['name' => 'Gifts', 'slug' => 'gifts'],
        ['name' => 'Pastries', 'slug' => 'pastries'],
        ['name' => 'Fresh Homemade Food', 'slug' => 'fresh-homemade-food'],
    ];

    public function run(): void
    {
        $cakes = Category::firstOrCreate(
            ['slug' => 'cakes'],
            ['name' => 'Cakes', 'is_active' => true, 'sort_order' => 0],
        );

        foreach (self::SUBCATEGORIES_OF_CAKES as $index => $category) {
            Category::firstOrCreate(
                ['slug' => $category['slug']],
                [
                    'parent_id' => $cakes->id,
                    'name' => $category['name'],
                    'is_active' => true,
                    'sort_order' => $index,
                ],
            );
        }

        foreach (self::TOP_LEVEL as $index => $category) {
            Category::firstOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
