<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Category;
use App\Models\Warehouse;
use App\Services\InventoryService;
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
        $harga = fake()->numberBetween(25, 350) * 1000;

        return [
            'kode_sku' => null,
            'judul' => fake()->sentence(3),
            'penulis' => fake()->name(),
            'penerbit' => fake()->company(),
            'tahun' => fake()->numberBetween(1990, now()->year),
            'isbn' => fake()->unique()->isbn13(),
            'sinopsis' => fake()->paragraph(),
            'harga' => $harga,
            'stok' => 0,
            'category_id' => Category::factory(),
            'cover_url' => null,
            'aktif' => true,
        ];
    }

    /**
     * Buku dengan cetakan default (cetakan ke-1).
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Book $book): void {
            // Buat edisi default kalau belum ada
            if ($book->editions()->doesntExist()) {
                $book->editions()->create([
                    'cetakan_ke' => 1,
                    'harga_beli' => (int) ($book->harga * 0.7),
                    'harga_jual' => $book->harga,
                    'is_active' => true,
                ]);
            }
        });
    }

    /**
     * Set stok awal di gudang Malang (dan sinkronkan agregat).
     */
    public function withStock(int $malang = 10, int $sidoarjo = 0, int $defect = 0): static
    {
        return $this->afterCreating(function (Book $book) use ($malang, $sidoarjo, $defect): void {
            $rows = [];

            if ($malang > 0) {
                $rows[] = ['kode' => 'malang', 'nama' => 'Malang', 'is_defect' => false, 'qty' => $malang];
            }

            if ($sidoarjo > 0) {
                $rows[] = ['kode' => 'sidoarjo', 'nama' => 'Sidoarjo', 'is_defect' => false, 'qty' => $sidoarjo];
            }

            if ($defect > 0) {
                $rows[] = ['kode' => 'defect', 'nama' => 'Defect', 'is_defect' => true, 'qty' => $defect];
            }

            foreach ($rows as $row) {
                $warehouse = Warehouse::firstOrCreate(
                    ['kode' => $row['kode']],
                    ['nama' => $row['nama'], 'is_defect' => $row['is_defect'], 'is_active' => true],
                );

                // Stok hidup di level cetakan (aktif/pertama); baris per buku
                // adalah mirror yang disinkronkan service.
                $edition = $book->activeEdition ?? $book->editions()->first();

                if ($edition !== null) {
                    $edition->stocks()->create([
                        'warehouse_id' => $warehouse->id,
                        'qty' => $row['qty'],
                    ]);
                } else {
                    $book->inventoryStocks()->create([
                        'warehouse_id' => $warehouse->id,
                        'qty' => $row['qty'],
                    ]);
                }
            }

            app(InventoryService::class)->syncBookStock($book);

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
     * Buku terjemahan dengan penterjemah.
     */
    public function translated(string $penterjemah = 'Penterjemah A'): static
    {
        return $this->state(fn (array $attributes) => [
            'penterjemah' => $penterjemah,
        ]);
    }
}
