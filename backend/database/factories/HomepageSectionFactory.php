<?php

namespace Database\Factories;

use App\Models\HomepageSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomepageSection>
 */
class HomepageSectionFactory extends Factory
{
    protected $model = HomepageSection::class;

    public function definition(): array
    {
        return [
            'type' => 'featured_products',
            'title' => fake()->words(3, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
