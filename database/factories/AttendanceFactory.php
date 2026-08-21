<?php

namespace Database\Factories;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $recognizedAt = now();

        return [
            'direction' => 'in',
            'method' => 'face',
            'confidence' => $this->faker->randomFloat(2, 80, 99),
            'recognized_at' => $recognizedAt,
            'source' => 'device',
            'dedupe_hash' => Attendance::makeDedupeHash(
                $this->faker->numberBetween(1, 1000000),
                null,
                'in',
                $recognizedAt->toIso8601String(),
            ),
        ];
    }
}
