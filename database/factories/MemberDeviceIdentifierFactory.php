<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\MemberDeviceIdentifier>
 */
class MemberDeviceIdentifierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_user_id' => Str::uuid()->toString(),
            'biometric_type' => 'face',
            'finger_position' => null,
            'enrolled_at' => now(),
            'consent_given_at' => now(),
        ];
    }
}
