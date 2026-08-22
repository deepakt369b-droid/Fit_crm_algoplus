<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WhatsappAutomation>
 */
class WhatsappAutomationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'trigger_type' => 'contact_created',
            'trigger_config' => null,
            'steps' => [],
            'status' => 'active',
        ];
    }
}
