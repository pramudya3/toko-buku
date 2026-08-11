<?php

namespace Database\Factories;

use App\Enums\FlowType;
use App\Models\CashFlow;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashFlow>
 */
class CashFlowFactory extends Factory
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
            'entry_date' => now()->toDateString(),
            'flow_type' => FlowType::Revenue,
            'amount' => fake()->numberBetween(50, 500) * 1000,
            'description' => null,
        ];
    }

    /**
     * Entry revenue.
     */
    public function revenue(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'flow_type' => FlowType::Revenue,
            'amount' => $amount,
        ]);
    }

    /**
     * Entry ongkir.
     */
    public function shipping(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'flow_type' => FlowType::Shipping,
            'amount' => $amount,
        ]);
    }

    /**
     * Entry refund.
     */
    public function refund(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'flow_type' => FlowType::Refund,
            'amount' => $amount,
        ]);
    }

    /**
     * Entry uang masuk manual.
     */
    public function income(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'flow_type' => FlowType::Income,
            'amount' => $amount,
        ]);
    }

    /**
     * Entry uang keluar manual.
     */
    public function expense(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'flow_type' => FlowType::Expense,
            'amount' => $amount,
        ]);
    }
}
