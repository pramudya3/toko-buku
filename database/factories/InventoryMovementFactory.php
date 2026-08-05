<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Enums\Warehouse;
use App\Models\Book;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
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
            'from_warehouse' => null,
            'to_warehouse' => Warehouse::Malang,
            'qty' => fake()->numberBetween(1, 20),
            'type' => MovementType::In,
            'reference' => null,
            'user_id' => User::factory(),
            'notes' => null,
        ];
    }

    /**
     * Mutasi transfer antar gudang.
     */
    public function transfer(Warehouse $from = Warehouse::Malang, Warehouse $to = Warehouse::Sidoarjo): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MovementType::Transfer,
            'from_warehouse' => $from,
            'to_warehouse' => $to,
        ]);
    }

    /**
     * Mutasi keluar.
     */
    public function out(Warehouse $from = Warehouse::Malang): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MovementType::Out,
            'from_warehouse' => $from,
            'to_warehouse' => null,
        ]);
    }

    /**
     * Mutasi defect.
     */
    public function defect(Warehouse $from = Warehouse::Malang): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MovementType::Defect,
            'from_warehouse' => $from,
            'to_warehouse' => Warehouse::Defect,
        ]);
    }
}
