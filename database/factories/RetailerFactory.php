<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Retailer>
 */
class RetailerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->create(['role' => 'retailer']),
            'shop_name' => fake()->company(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'image' => null,
            'age' => fake()->numberBetween(18, 65),
        ];
    }
}