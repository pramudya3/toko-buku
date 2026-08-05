<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\InventoryStock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryStock>
 */
class InventoryStockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'stock_malang' => fake()->numberBetween(0, 50),
            'stock_sidoarjo' => fake()->numberBetween(0, 30),
            'stock_defect' => fake()->numberBetween(0, 5),
        ];
    }

    /**
     * Stok menipis (≤ ambang batas).
     */
    public function lowStock(int $total = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_malang' => $total,
            'stock_sidoarjo' => 0,
            'stock_defect' => 0,
        ]);
    }
}
