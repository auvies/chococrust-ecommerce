<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => ucwords($name),
            'slug' => str($name)->slug(),
            'description' => fake()->paragraph(),
            'short_description' => fake()->sentence(),
            'status' => 'active',
            'is_featured' => false,
        ];
    }
}
