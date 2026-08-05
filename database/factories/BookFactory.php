<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode_sku' => null,
            'judul' => fake()->sentence(3),
            'penulis' => fake()->name(),
            'penerbit' => fake()->company(),
            'tahun' => fake()->numberBetween(1990, now()->year),
            'isbn' => fake()->unique()->isbn13(),
            'sinopsis' => fake()->paragraph(),
            'harga' => fake()->numberBetween(25, 350) * 1000,
            'stok' => 0,
            'category_id' => Category::factory(),
            'cover_url' => null,
            'aktif' => true,
            'is_preorder' => false,
            'po_label' => null,
        ];
    }

    /**
     * Set stok awal di gudang Malang (dan sinkronkan agregat).
     */
    public function withStock(int $malang = 10, int $sidoarjo = 0, int $defect = 0): static
    {
        return $this->afterCreating(function (Book $book) use ($malang, $sidoarjo, $defect): void {
            $book->inventoryStock()->create([
                'stock_malang' => $malang,
                'stock_sidoarjo' => $sidoarjo,
                'stock_defect' => $defect,
            ]);

            $book->update(['stok' => $malang + $sidoarjo]);
        });
    }

    /**
     * Buku nonaktif (soft-disable).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'aktif' => false,
        ]);
    }

    /**
     * Buku preorder.
     */
    public function preorder(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_preorder' => true,
            'po_label' => 'PO - '.(now()->addMonths(2)->format('M Y')),
        ]);
    }
}
