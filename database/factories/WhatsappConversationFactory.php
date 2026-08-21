<?php

namespace Database\Factories;

use App\Models\WhatsappContact;
use App\Models\WhatsappPhoneNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WhatsappConversation>
 */
class WhatsappConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wa_phone_number_id' => WhatsappPhoneNumber::factory(),
            'wa_contact_id' => WhatsappContact::factory(),
            'status' => 'open',
            'unread_count' => 0,
        ];
    }
}
