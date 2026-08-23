<?php

namespace Database\Factories;

use App\Models\AiUsageLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsageLog>
 */
class AiUsageLogFactory extends Factory
{
    protected $model = AiUsageLog::class;

    public function definition(): array
    {
        return [
            'provider' => 'internal',
            'model' => 'deterministic',
            'purpose' => 'chat_reply',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cost_usd' => 0,
            'status' => 'success',
        ];
    }
}
