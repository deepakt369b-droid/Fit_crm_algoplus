<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\WhatsappContact>
 */
class WhatsappContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = $this->faker->unique()->numerify('1##########');

        return [
            'phone' => $phone,
            'wa_id' => $phone,
            'name' => $this->faker->name(),
            'opt_in_status' => 'unknown',
            'source' => 'inbound',
        ];
    }
}
