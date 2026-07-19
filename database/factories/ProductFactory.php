<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category_id' => null,
            'subcategory_id' => null,
            'unit' => fake()->randomElement(['kg', 'pcs', 'liters', 'pack']),
            'price' => fake()->randomFloat(2, 10, 500),
            'quantity' => fake()->numberBetween(0, 200),
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ];
    }
}