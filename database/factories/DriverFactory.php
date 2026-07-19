<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vehicle_type' => fake()->randomElement(['car', 'motorcycle', 'bicycle']),
            'license_number' => fake()->bothify('DL-#######'),
            'rating' => fake()->numberBetween(30, 50) / 10, // 3.0 to 5.0
            'status' => 'available',
            'current_location' => fake()->city(),
        ];
    }
}