<?php

namespace Database\Factories;

use App\Models\HeroBanner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeroBanner>
 */
class HeroBannerFactory extends Factory
{
    protected $model = HeroBanner::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'image_url' => fake()->imageUrl(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
