<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WhatsappTemplate>
 */
class WhatsappTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meta_template_id' => $this->faker->numerify('##############'),
            'name' => $this->faker->unique()->slug(2, false),
            'language' => 'en_US',
            'category' => 'MARKETING',
            'status' => 'approved',
            'components' => [
                ['type' => 'BODY', 'text' => 'Hello {{1}}, this is a test template.'],
            ],
            'synced_at' => now(),
        ];
    }
}
