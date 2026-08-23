<?php

namespace Database\Factories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    public function definition(): array
    {
        return [
            'key' => 'setting.'.fake()->unique()->word(),
            'value' => 'sample',
            'type' => 'string',
            'is_public' => false,
        ];
    }
}
