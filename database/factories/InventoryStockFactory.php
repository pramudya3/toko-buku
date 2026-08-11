<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\InventoryStock;
use App\Models\Warehouse;
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
        $warehouse = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang']);

        return [
            'book_id' => Book::factory(),
            'warehouse_id' => $warehouse->id,
            'qty' => fake()->numberBetween(0, 50),
        ];
    }

    /**
     * Stok menipis (≤ ambang batas).
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'qty' => fake()->numberBetween(0, config('pricing.low_stock_threshold', 5)),
        ]);
    }
}
