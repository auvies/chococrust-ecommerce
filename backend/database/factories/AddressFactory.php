<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'type' => 'both',
            'recipient_name' => fake()->name(),
            'phone' => fake()->numerify('03#########'),
            'line1' => fake()->streetAddress(),
            'city' => 'Karachi',
            'country' => 'PK',
            'is_default' => false,
        ];
    }
}
