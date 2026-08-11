<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->slug(2),
            'nama' => fake()->unique()->city(),
            'alamat' => fake()->optional()->address(),
            'is_defect' => false,
            'is_active' => true,
        ];
    }

    /**
     * Gudang khusus defect.
     */
    public function defect(): static
    {
        return $this->state(fn (array $attributes) => [
            'kode' => 'defect',
            'nama' => 'Defect',
            'is_defect' => true,
        ]);
    }

    /**
     * Gudang default Malang.
     */
    public function malang(): static
    {
        return $this->state(fn (array $attributes) => [
            'kode' => 'malang',
            'nama' => 'Malang',
            'is_defect' => false,
        ]);
    }
}
