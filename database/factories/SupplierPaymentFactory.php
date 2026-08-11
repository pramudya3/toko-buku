<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierPayment>
 */
class SupplierPaymentFactory extends Factory
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
            'payment_date' => fake()->date(),
            'amount' => fake()->numberBetween(50_000, 2_000_000),
            'notes' => fake()->optional()->sentence(),
            'user_id' => null,
        ];
    }
}
