<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->numberBetween(25, 300) * 1000;

        return [
            'order_id' => Order::factory(),
            'book_id' => Book::factory(),
            'judul_snapshot' => fn () => fake()->sentence(3),
            'harga_snapshot' => $price,
            'qty' => fake()->numberBetween(1, 5),
            'price_original' => $price,
            'promo_discount_amount' => 0,
            'tier_discount_amount' => 0,
            'price_final' => $price,
        ];
    }
}
