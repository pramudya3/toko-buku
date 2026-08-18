<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\BookEdition;
use App\Models\Category;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * 20 buku demo storefront — kategori + edisi + stok gudang.
 *
 * Idempotent: kategori firstOrCreate; buku dibuat berdasarkan ISBN unik
 * (bila ISBN null, berdasarkan kombinasi judul+penulis) agar aman dipanggil
 * ulang tanpa duplikat.
 */
class BookSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            ['nama' => 'Fiksi', 'slug' => 'fiksi'],
            ['nama' => 'Non-Fiksi', 'slug' => 'non-fiksi'],
            ['nama' => 'Anak & Remaja', 'slug' => 'anak-remaja'],
            ['nama' => 'Religi', 'slug' => 'religi'],
            ['nama' => 'Pendidikan', 'slug' => 'pendidikan'],
        ])->mapWithKeys(fn (array $data): array => [
            $data['slug'] => Category::firstOrCreate(
                ['nama' => $data['nama']],
                ['nama' => $data['nama']],
            ),
        ]);

        $bookData = [
            // Fiksi
            ['judul' => 'Laskar Pelangi', 'penulis' => 'Andrea Hirata', 'harga' => 85000, 'isbn' => '9789793062792', 'kategori' => 'fiksi', 'stok' => [12, 5]],
            ['judul' => 'Bumi Manusia', 'penulis' => 'Pramoedya Ananta Toer', 'harga' => 95000, 'isbn' => '9789799731234', 'kategori' => 'fiksi', 'stok' => [8, 0]],
            ['judul' => 'Negeri 5 Menara', 'penulis' => 'Ahmad Fuadi', 'harga' => 75000, 'isbn' => '9789793062614', 'kategori' => 'fiksi', 'stok' => [2, 0]],
            ['judul' => 'Pulang', 'penulis' => 'Tere Liye', 'harga' => 94000, 'isbn' => '9786020301083', 'kategori' => 'fiksi', 'stok' => [10, 3]],
            ['judul' => 'Ronggeng Dukuh Paruk', 'penulis' => 'Ahmad Tohari', 'harga' => 88000, 'isbn' => '9789799731227', 'kategori' => 'fiksi', 'stok' => [6, 2]],
            ['judul' => 'Gadis Pantai', 'penulis' => 'Pramoedya Ananta Toer', 'harga' => 70000, 'isbn' => '9789799731241', 'kategori' => 'fiksi', 'stok' => [0, 4]],
            // Non-Fiksi
            ['judul' => 'Filosofi Teras', 'penulis' => 'Henry Manampiring', 'harga' => 98000, 'isbn' => '9786020643091', 'kategori' => 'non-fiksi', 'stok' => [14, 4]],
            ['judul' => 'Atomic Habits', 'penulis' => 'James Clear', 'harga' => 102000, 'isbn' => '9786020633180', 'kategori' => 'non-fiksi', 'stok' => [20, 10]],
            ['judul' => 'Psychology of Money', 'penulis' => 'Morgan Housel', 'harga' => 98000, 'isbn' => '9786020633181', 'kategori' => 'non-fiksi', 'stok' => [9, 9]],
            ['judul' => 'Sapiens: Riwayat Singkat Umat Manusia', 'penulis' => 'Yuval Noah Harari', 'harga' => 125000, 'isbn' => '9786020382211', 'kategori' => 'non-fiksi', 'stok' => [7, 3]],
            ['judul' => '21 Lessons for the 21st Century', 'penulis' => 'Yuval Noah Harari', 'harga' => 118000, 'isbn' => '9786020382228', 'kategori' => 'non-fiksi', 'stok' => [5, 5]],
            ['judul' => 'The Power of Habit', 'penulis' => 'Charles Duhigg', 'harga' => 110000, 'isbn' => '9786020302158', 'kategori' => 'non-fiksi', 'stok' => [11, 2]],
            // Anak & Remaja
            ['judul' => 'Si Anak Kuat', 'penulis' => 'Tere Liye', 'harga' => 89000, 'isbn' => '9786020641950', 'kategori' => 'anak-remaja', 'stok' => [15, 6]],
            ['judul' => 'Si Anak Pemberani', 'penulis' => 'Tere Liye', 'harga' => 89000, 'isbn' => '9786020641967', 'kategori' => 'anak-remaja', 'stok' => [13, 4]],
            ['judul' => 'Dunia Sophie', 'penulis' => 'Jostein Gaarder', 'harga' => 135000, 'isbn' => '9789793062591', 'kategori' => 'anak-remaja', 'stok' => [4, 1]],
            ['judul' => 'Petualangan Si Bungsu', 'penulis' => 'Dewi Lestari', 'harga' => 65000, 'isbn' => '9786020641974', 'kategori' => 'anak-remaja', 'stok' => [18, 7]],
            // Religi
            ['judul' => 'Tafsir Al-Mishbah', 'penulis' => 'M. Quraish Shihab', 'harga' => 250000, 'isbn' => '9789794336980', 'kategori' => 'religi', 'stok' => [5, 3]],
            ['judul' => 'Riyadhus Shalihin', 'penulis' => 'Imam An-Nawawi', 'harga' => 145000, 'isbn' => '9789794336997', 'kategori' => 'religi', 'stok' => [8, 2]],
            ['judul' => 'Membaca Al-Qur\'an dengan Tartil', 'penulis' => 'Ustadz Adi Hidayat', 'harga' => 78000, 'isbn' => '9789794337000', 'kategori' => 'religi', 'stok' => [10, 5]],
            // Pendidikan
            ['judul' => 'Matematika SMA Kelas 10', 'penulis' => 'Kemendikbud', 'harga' => 65000, 'isbn' => '9786022445878', 'kategori' => 'pendidikan', 'stok' => [0, 25]],
        ];

        $inventory = app(InventoryService::class);

        // Ambil URL cover dari Open Library Covers API (by ISBN) — cover
        // nyata buku, gratis tanpa API key. Gagal/offline → fallback picsum.
        $coverCache = [];

        $coverFor = function (string $isbn) use (&$coverCache): ?string {
            if (isset($coverCache[$isbn])) {
                return $coverCache[$isbn];
            }

            $url = null;

            try {
                // Redirect-nya diikuti otomatis; status 200 = cover ada.
                $response = Http::timeout(5)->get(
                    "https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg",
                );

                if ($response->successful()) {
                    $url = "https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg";
                }
            } catch (Throwable) {
                // Offline / timeout — fallback di bawah.
            }

            $coverCache[$isbn] = $url;

            return $url;
        };

        foreach ($bookData as $data) {
            // Cari buku yang sudah ada (ISBN unik) — jangan duplikat saat seed ulang.
            $book = Book::where('isbn', $data['isbn'])->first();

            if ($book === null) {
                $book = Book::create([
                    'judul' => $data['judul'],
                    'penulis' => $data['penulis'],
                    'penerbit' => fake()->randomElement(['Gramedia Pustaka Utama', 'Bentang Pustaka', 'Republika', 'Gagas Media', 'Mizan']),
                    'tahun' => fake()->numberBetween(2005, now()->year),
                    'isbn' => $data['isbn'],
                    'sinopsis' => fake()->paragraph(3),
                    'harga' => $data['harga'],
                    'stok' => 0,
                    'category_id' => $categories[$data['kategori']]->id,
                    'aktif' => true,
                    'berat_gr' => fake()->numberBetween(150, 900),
                    'jumlah_halaman' => fake()->numberBetween(96, 640),
                    'cover_url' => $coverFor($data['isbn'])
                        ?? "https://picsum.photos/seed/{$data['isbn']}/400/600",
                ]);

                // Cetakan ke-1 (harga beli ≈ 65% dari harga jual).
                $edition = BookEdition::create([
                    'book_id' => $book->id,
                    'cetakan_ke' => 1,
                    'harga_beli' => (int) round($data['harga'] * 0.65),
                    'harga_jual' => $data['harga'],
                    'is_active' => true,
                ]);

                // Stok di gudang Malang & Sidoarjo.
                foreach ([['kode' => 'malang', 'qty' => $data['stok'][0]], ['kode' => 'sidoarjo', 'qty' => $data['stok'][1]]] as $spec) {
                    $warehouse = Warehouse::query()->where('kode', $spec['kode'])->first();

                    if ($warehouse !== null && $spec['qty'] > 0) {
                        $edition->stocks()->create(['warehouse_id' => $warehouse->id, 'qty' => $spec['qty']]);
                    }
                }

                $inventory->syncBookStock($book);
            } elseif ($book->cover_url === null || str_contains($book->cover_url, 'picsum.photos')) {
                // Buku dari seed sebelumnya tanpa cover / masih fallback acak →
                // coba isi cover nyata dari Open Library.
                $book->update([
                    'cover_url' => $coverFor($data['isbn'])
                        ?? "https://picsum.photos/seed/{$data['isbn']}/400/600",
                ]);
            }
        }

        $this->command->info('BookSeeder selesai: 20 buku demo tersedia.');
    }
}
