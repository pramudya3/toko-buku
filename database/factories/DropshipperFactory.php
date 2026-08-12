<?php

namespace Database\Factories;

use App\Models\Dropshipper;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dropshipper>
 */
class DropshipperFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'end_customer_name' => fake()->name(),
            'end_customer_whatsapp' => fake()->numerify('08##########'),
            'end_customer_address' => fake()->address(),
        ];
    }
}
