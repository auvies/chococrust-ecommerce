<?php

namespace Database\Seeders;

use App\Models\Theme;
use Illuminate\Database\Seeder;

/**
 * At least 10 storefront themes (business brief), each with a complete
 * config: background/surface/text colors, primary/secondary brand colors,
 * typography (body + heading font family), a font-size scale, and button
 * styling (radius + primary/secondary colors) - the exact shape
 * StoreThemeRequest/UpdateThemeRequest validate against, so every seeded
 * theme is also a worked example of a valid config.
 *
 * `firstOrCreate` keyed on slug, matching every other content seeder's
 * idempotency contract (CategorySeeder, DeliveryRuleSeeder, ...) - re-
 * running this never overwrites a color an admin edited afterward.
 */
class ThemeSeeder extends Seeder
{
    private const FONT_SIZES = ['sm' => '14px', 'base' => '16px', 'lg' => '18px', 'xl' => '24px', '2xl' => '32px'];

    private const THEMES = [
        [
            'name' => 'Classic Chocolate', 'slug' => 'classic-chocolate', 'is_active' => true,
            'background' => '#FFFBF5', 'surface' => '#F5EDE4', 'text_color' => '#2B1B12',
            'primary_color' => '#5B2E1E', 'secondary_color' => '#D97706',
            'font_family' => "'Nunito', sans-serif", 'heading_font_family' => "'Playfair Display', serif",
            'button_radius' => '8px',
        ],
        [
            'name' => 'Midnight Cocoa', 'slug' => 'midnight-cocoa', 'is_active' => false,
            'background' => '#1C1310', 'surface' => '#2B211B', 'text_color' => '#F5EDE4',
            'primary_color' => '#C08552', 'secondary_color' => '#E8B75E',
            'font_family' => "'Inter', sans-serif", 'heading_font_family' => "'Cormorant Garamond', serif",
            'button_radius' => '6px',
        ],
        [
            'name' => 'Rose Gold', 'slug' => 'rose-gold', 'is_active' => false,
            'background' => '#FFF5F5', 'surface' => '#FCE8E8', 'text_color' => '#3D2224',
            'primary_color' => '#B76E79', 'secondary_color' => '#E8B4B8',
            'font_family' => "'Quicksand', sans-serif", 'heading_font_family' => "'DM Serif Display', serif",
            'button_radius' => '9999px',
        ],
        [
            'name' => 'Mint Fresh', 'slug' => 'mint-fresh', 'is_active' => false,
            'background' => '#F4FBF8', 'surface' => '#E3F5EC', 'text_color' => '#163829',
            'primary_color' => '#2F9E6B', 'secondary_color' => '#7FD8A6',
            'font_family' => "'Poppins', sans-serif", 'heading_font_family' => "'Fraunces', serif",
            'button_radius' => '8px',
        ],
        [
            'name' => 'Pastel Bakery', 'slug' => 'pastel-bakery', 'is_active' => false,
            'background' => '#FFF9F0', 'surface' => '#FCEFE0', 'text_color' => '#4A3B33',
            'primary_color' => '#E8A87C', 'secondary_color' => '#C38D9E',
            'font_family' => "'Nunito', sans-serif", 'heading_font_family' => "'Lora', serif",
            'button_radius' => '9999px',
        ],
        [
            'name' => 'Royal Purple', 'slug' => 'royal-purple', 'is_active' => false,
            'background' => '#FAF7FD', 'surface' => '#F0E8FA', 'text_color' => '#2E1A47',
            'primary_color' => '#6B3FA0', 'secondary_color' => '#B389D6',
            'font_family' => "'Raleway', sans-serif", 'heading_font_family' => "'Playfair Display', serif",
            'button_radius' => '4px',
        ],
        [
            'name' => 'Ocean Breeze', 'slug' => 'ocean-breeze', 'is_active' => false,
            'background' => '#F2FAFC', 'surface' => '#E1F3F8', 'text_color' => '#163B44',
            'primary_color' => '#1E7B8C', 'secondary_color' => '#6FC3D6',
            'font_family' => "'Inter', sans-serif", 'heading_font_family' => "'Merriweather', serif",
            'button_radius' => '8px',
        ],
        [
            'name' => 'Autumn Spice', 'slug' => 'autumn-spice', 'is_active' => false,
            'background' => '#FFF8F0', 'surface' => '#FBE9D6', 'text_color' => '#402A1B',
            'primary_color' => '#B85C38', 'secondary_color' => '#E0A458',
            'font_family' => "'Quicksand', sans-serif", 'heading_font_family' => "'Fraunces', serif",
            'button_radius' => '6px',
        ],
        [
            'name' => 'Monochrome', 'slug' => 'monochrome', 'is_active' => false,
            'background' => '#FFFFFF', 'surface' => '#F2F2F2', 'text_color' => '#111111',
            'primary_color' => '#1A1A1A', 'secondary_color' => '#6B6B6B',
            'font_family' => "'Inter', sans-serif", 'heading_font_family' => "'Inter', sans-serif",
            'button_radius' => '2px',
        ],
        [
            'name' => 'Berry Bliss', 'slug' => 'berry-bliss', 'is_active' => false,
            'background' => '#FDF5F8', 'surface' => '#F9E4EC', 'text_color' => '#3A0F22',
            'primary_color' => '#9C2963', 'secondary_color' => '#D6577E',
            'font_family' => "'Poppins', sans-serif", 'heading_font_family' => "'DM Serif Display', serif",
            'button_radius' => '9999px',
        ],
        [
            'name' => 'Golden Hour', 'slug' => 'golden-hour', 'is_active' => false,
            'background' => '#FFFCF2', 'surface' => '#FBF0D0', 'text_color' => '#4A3B0E',
            'primary_color' => '#C9971C', 'secondary_color' => '#E8C468',
            'font_family' => "'Nunito', sans-serif", 'heading_font_family' => "'Cormorant Garamond', serif",
            'button_radius' => '8px',
        ],
        [
            'name' => 'Forest Green', 'slug' => 'forest-green', 'is_active' => false,
            'background' => '#F5FAF6', 'surface' => '#E4F0E6', 'text_color' => '#16301F',
            'primary_color' => '#2D5D3F', 'secondary_color' => '#6FA382',
            'font_family' => "'Raleway', sans-serif", 'heading_font_family' => "'Lora', serif",
            'button_radius' => '6px',
        ],
    ];

    public function run(): void
    {
        foreach (self::THEMES as $theme) {
            Theme::firstOrCreate(
                ['slug' => $theme['slug']],
                [
                    'name' => $theme['name'],
                    'is_active' => $theme['is_active'],
                    'config' => [
                        'background' => $theme['background'],
                        'surface' => $theme['surface'],
                        'text_color' => $theme['text_color'],
                        'primary_color' => $theme['primary_color'],
                        'secondary_color' => $theme['secondary_color'],
                        'typography' => [
                            'font_family' => $theme['font_family'],
                            'heading_font_family' => $theme['heading_font_family'],
                        ],
                        'font_sizes' => self::FONT_SIZES,
                        'buttons' => [
                            'radius' => $theme['button_radius'],
                            'primary_bg' => $theme['primary_color'],
                            'primary_text' => '#FFFFFF',
                            'secondary_bg' => $theme['secondary_color'],
                            'secondary_text' => $theme['text_color'],
                        ],
                    ],
                ],
            );
        }
    }
}
