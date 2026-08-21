<?php

namespace Database\Factories;

use App\Models\WhatsappConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WhatsappMessage>
 */
class WhatsappMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wa_conversation_id' => WhatsappConversation::factory(),
            'direction' => 'in',
            'type' => 'text',
            'status' => 'delivered',
            'body' => $this->faker->sentence(),
            'occurred_at' => now(),
        ];
    }
}
