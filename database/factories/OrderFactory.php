<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Retailer;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $retailer = Retailer::inRandomOrder()->first() ?? Retailer::factory()->create();
        $user = $retailer->user;

        return [
            'order_number' => 'ORD-' . fake()->unique()->numerify('########'),
            'retailer_id' => $retailer->id,
            'status' => fake()->randomElement(['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled']),
            'subtotal' => fake()->randomFloat(2, 50, 500),
            'discount' => 0,
            'delivery_fee' =>fake()->randomFloat(2, 3, 15),
            'total' => fake()->randomFloat(2, 60, 530),
        ];
    }
}