<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number' => 'CC-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
            'customer_id' => Customer::factory(),
            'status' => 'pending',
            'subtotal' => 1000,
            'total' => 1000,
            'delivery_type' => 'local',
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->numerify('03#########'),
            'placed_at' => now(),
        ];
    }
}
