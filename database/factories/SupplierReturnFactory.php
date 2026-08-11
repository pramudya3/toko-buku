<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierReturn>
 */
class SupplierReturnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'supplier_purchase_id' => null,
            'return_date' => fake()->date(),
            'total' => fake()->numberBetween(10_000, 500_000),
            'notes' => fake()->optional()->sentence(),
            'user_id' => null,
        ];
    }
}
