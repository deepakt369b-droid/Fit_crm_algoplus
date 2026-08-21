<?php

namespace Database\Factories;

use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WhatsappBroadcast>
 */
class WhatsappBroadcastFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // The template must belong to the same phone number as the
        // broadcast, so create one phone number and hang both off it
        // rather than letting each FK spawn its own via nested factories.
        $phoneNumber = WhatsappPhoneNumber::factory()->create();

        return [
            'wa_phone_number_id' => $phoneNumber->id,
            'wa_template_id' => WhatsappTemplate::factory()->for($phoneNumber, 'phoneNumber')->create()->id,
            'name' => $this->faker->sentence(3),
            'status' => 'draft',
        ];
    }
}
