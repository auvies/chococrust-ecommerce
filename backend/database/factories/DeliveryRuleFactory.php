<?php

namespace Database\Factories;

use App\Models\DeliveryRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryRule>
 */
class DeliveryRuleFactory extends Factory
{
    protected $model = DeliveryRule::class;

    public function definition(): array
    {
        return [
            'scope' => 'global',
            'category_id' => null,
            'product_id' => null,
            'delivery_type' => 'both',
            'is_deliverable' => true,
            'min_order_amount' => null,
            'flat_fee' => fake()->randomFloat(2, 0, 500),
            'priority' => 0,
            'is_active' => true,
        ];
    }
}
