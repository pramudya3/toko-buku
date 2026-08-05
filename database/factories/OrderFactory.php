<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Warehouse;
use App\Models\Order;
use App\Models\User;
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
            'no_order' => 'ORD-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'user_id' => User::factory(),
            'nama_pembeli' => fake()->name(),
            'no_hp' => fake()->phoneNumber(),
            'alamat' => fake()->address(),
            'metode_bayar' => PaymentMethod::Transfer,
            'total' => 0,
            'shipping_cost' => 0,
            'is_dropship' => false,
            'warehouse_origin' => null,
            'status' => OrderStatus::MenungguKonfirmasi,
            'ekspedisi' => null,
            'ongkir_estimasi' => null,
        ];
    }

    /**
     * Order dengan status tertentu.
     */
    public function status(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * Order dropship.
     */
    public function dropship(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_dropship' => true,
        ]);
    }

    /**
     * Order yang sudah diproses (ongkir + gudang asal terisi).
     */
    public function processed(string $shippingCost = '15000', Warehouse $warehouse = Warehouse::Malang): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Diproses,
            'shipping_cost' => (int) $shippingCost,
            'ekspedisi' => 'jne',
            'warehouse_origin' => $warehouse,
        ]);
    }
}
