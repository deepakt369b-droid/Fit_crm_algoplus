<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WhatsappAiSetting>
 */
class WhatsappAiSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'anthropic_api_key' => 'sk-ant-test-'.$this->faker->uuid(),
            'model' => 'claude-opus-5',
            'system_prompt' => null,
        ];
    }
}
