<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierPurchase>
 */
class SupplierPurchaseFactory extends Factory
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
            'ref_code' => 'PO-'.now()->format('Ymd').'-'.fake()->unique()->numberBetween(1000, 9999),
            'purchase_date' => fake()->date(),
            'total' => fake()->numberBetween(100_000, 5_000_000),
            'notes' => fake()->optional()->sentence(),
            'user_id' => null,
        ];
    }
}
