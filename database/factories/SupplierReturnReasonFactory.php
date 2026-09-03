<?php

namespace Database\Factories;

use App\Models\SupplierReturnReason;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupplierReturnReason>
 */
class SupplierReturnReasonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Halaman Rusak',
            'Jilid Lepas',
            'Salah Kirim Judul',
            'Salah Jumlah',
            'Cacat Cetak',
            'Buku Kotor',
        ]);

        return [
            'code' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'name' => $name,
            'category' => fake()->randomElement(['umum', 'cacat', 'salah_kirim']),
            'type' => 'supplier',
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
