<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\Book;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Models\Warehouse;
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
        $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang']);

        return [
            'book_id' => Book::factory(),
            'from_warehouse_id' => null,
            'to_warehouse_id' => $malang->id,
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
    public function transfer(Warehouse $from, Warehouse $to): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MovementType::Transfer,
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
        ]);
    }

    /**
     * Mutasi keluar.
     */
    public function out(Warehouse $from): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MovementType::Out,
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => null,
        ]);
    }

    /**
     * Mutasi defect.
     */
    public function defect(Warehouse $from): static
    {
        $defect = Warehouse::firstOrCreate(['kode' => 'defect'], ['nama' => 'Defect', 'is_defect' => true]);

        return $this->state(fn (array $attributes) => [
            'type' => MovementType::Defect,
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $defect->id,
        ]);
    }
}
