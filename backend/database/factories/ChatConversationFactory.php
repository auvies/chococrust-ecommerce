<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatConversation>
 */
class ChatConversationFactory extends Factory
{
    protected $model = ChatConversation::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'session_id' => (string) Str::uuid(),
            'channel' => 'web_widget',
            'status' => 'open',
            'started_at' => now(),
        ];
    }
}
