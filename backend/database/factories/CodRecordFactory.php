<?php

namespace Database\Factories;

use App\Models\CodRecord;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodRecord>
 */
class CodRecordFactory extends Factory
{
    protected $model = CodRecord::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'payment_id' => Payment::factory(),
            'status' => 'awaiting_delivery',
            'amount_due' => 1000,
        ];
    }
}
