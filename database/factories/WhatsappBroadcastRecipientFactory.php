<?php

namespace Database\Factories;

use App\Models\WhatsappBroadcast;
use App\Models\WhatsappContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WhatsappBroadcastRecipient>
 */
class WhatsappBroadcastRecipientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wa_broadcast_id' => WhatsappBroadcast::factory(),
            'wa_contact_id' => WhatsappContact::factory(),
            'status' => 'pending',
        ];
    }
}
