<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
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
        return [
            'order_number' => fake()->uuid(),
            'cart_id' => Cart::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->e164PhoneNumber(),
            'shipping_address' => fake()->address(),
            'amount_paise' => fake()->numberBetween(100, 100000),
            'currency' => 'INR',
            'status' => 'pending',
        ];
    }
}
