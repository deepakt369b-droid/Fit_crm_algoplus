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
            'provider' => 'anthropic',
            'api_key' => 'sk-ant-test-'.$this->faker->uuid(),
            'base_url' => null,
            'model' => 'claude-opus-5',
            'system_prompt' => null,
        ];
    }
}
