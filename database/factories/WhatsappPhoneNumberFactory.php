<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WhatsappPhoneNumber>
 */
class WhatsappPhoneNumberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'waba_id' => $this->faker->numerify('##############'),
            'phone_number_id' => $this->faker->unique()->numerify('##############'),
            'display_phone_number' => $this->faker->numerify('+1##########'),
            'verified_name' => $this->faker->company(),
            'access_token' => 'test-token-'.$this->faker->uuid(),
            'is_shared' => false,
            'status' => 'active',
        ];
    }
}
