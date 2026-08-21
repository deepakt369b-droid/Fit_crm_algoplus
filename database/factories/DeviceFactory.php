<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true).' Gate',
            'type' => $this->faker->randomElement(['face', 'fingerprint', 'hybrid']),
            'location' => $this->faker->randomElement(['Main Entrance', 'Side Door', 'Locker Room']),
            'serial' => $this->faker->unique()->bothify('DEV-????????'),
            'status' => 'paired',
            'paired_at' => now(),
            'last_seen_at' => now(),
        ];
    }
}
