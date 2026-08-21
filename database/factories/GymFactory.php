<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Gym>
 */
class GymFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->company().' Fitness';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1000, 9999),
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'email' => $this->faker->unique()->companyEmail(),
            'contact' => $this->faker->phoneNumber(),
            'address' => $this->faker->streetAddress(),
            'country' => $this->faker->country(),
            'state' => $this->faker->state(),
            'city' => $this->faker->city(),
            'pincode' => $this->faker->postcode(),
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'active',
        ];
    }
}
