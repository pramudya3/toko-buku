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
 * 36 buku demo storefront — kategori + edisi + stok gudang.
 *
 * Data acak (judul/penulis pools, harga & stok random) agar katalog
 * terlihat hidup setelah reset data.
 *
 * Idempotent: kategori firstOrCreate; buku dibuat berdasarkan ISBN unik
 * (deterministik per judul) agar aman dipanggil ulang tanpa duplikat.
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
            ['judul' => 'Senja di Ujung Langit', 'penulis' => 'Ratih Prameswari', 'kategori' => 'fiksi'],
            ['judul' => 'Hujan dan Kenangan', 'penulis' => 'Fajar Nugroho', 'kategori' => 'fiksi'],
            ['judul' => 'Kota Tanpa Nama', 'penulis' => 'Sinta Maharani', 'kategori' => 'fiksi'],
            ['judul' => 'Jejak Kaki di Pasir', 'penulis' => 'Bambang Sutrisno', 'kategori' => 'fiksi'],
            ['judul' => 'Malam Seribu Bintang', 'penulis' => 'Dian Puspita', 'kategori' => 'fiksi'],
            ['judul' => 'Arus Balik', 'penulis' => 'Hendra Gunawan', 'kategori' => 'fiksi'],
            ['judul' => 'Taman Rahasia', 'penulis' => 'Lestari Wulandari', 'kategori' => 'fiksi'],
            ['judul' => 'Perahu Kertas Biru', 'penulis' => 'Yoga Saputra', 'kategori' => 'fiksi'],
            // Non-Fiksi
            ['judul' => 'Seni Berpikir Jernih', 'penulis' => 'Agus Wijaya', 'kategori' => 'non-fiksi'],
            ['judul' => 'Kebiasaan Kecil Hasil Besar', 'penulis' => 'Rina Kusuma', 'kategori' => 'non-fiksi'],
            ['judul' => 'Psikologi Uang untuk Pemula', 'penulis' => 'Dedi Kurniawan', 'kategori' => 'non-fiksi'],
            ['judul' => 'Sejarah Nusantara Singkat', 'penulis' => 'Slamet Riyadi', 'kategori' => 'non-fiksi'],
            ['judul' => 'Produktif Tanpa Stres', 'penulis' => 'Maya Anggraini', 'kategori' => 'non-fiksi'],
            ['judul' => 'Bahasa Tubuh Sehari-hari', 'penulis' => 'Andi Pratama', 'kategori' => 'non-fiksi'],
            ['judul' => 'Investasi Saham untuk Pemula', 'penulis' => 'Budi Hartono', 'kategori' => 'non-fiksi'],
            ['judul' => 'Filosofi Kopi Pagi', 'penulis' => 'Tono Prasetyo', 'kategori' => 'non-fiksi'],
            // Anak & Remaja
            ['judul' => 'Petualangan Kiki dan Kiko', 'penulis' => 'Nina Zatul', 'kategori' => 'anak-remaja'],
            ['judul' => 'Si Komodo Pemberani', 'penulis' => 'Rudi Santoso', 'kategori' => 'anak-remaja'],
            ['judul' => 'Sekolah Ajaib Pelangi', 'penulis' => 'Fitri Handayani', 'kategori' => 'anak-remaja'],
            ['judul' => 'Robot Kecil Penjelajah', 'penulis' => 'Eko Purnomo', 'kategori' => 'anak-remaja'],
            ['judul' => 'Dongeng Nusantara Pilihan', 'penulis' => 'Sari Melati', 'kategori' => 'anak-remaja'],
            ['judul' => 'Detektif Cilik: Misteri Kue Hilang', 'penulis' => 'Joko Susilo', 'kategori' => 'anak-remaja'],
            ['judul' => 'Putri Bintang Laut', 'penulis' => 'Ayu Lestari', 'kategori' => 'anak-remaja'],
            // Religi
            ['judul' => 'Tuntunan Shalat Lengkap', 'penulis' => 'Hidayatullah', 'kategori' => 'religi'],
            ['judul' => 'Kisah 25 Nabi dan Rasul', 'penulis' => 'Tim Pustaka Ilmu', 'kategori' => 'religi'],
            ['judul' => 'Doa Sehari-hari Anak Muslim', 'penulis' => 'Maryam Zakaria', 'kategori' => 'religi'],
            ['judul' => 'Akhlak Mulia dalam Islam', 'penulis' => 'Abdul Karim', 'kategori' => 'religi'],
            ['judul' => 'Panduan Haji dan Umrah', 'penulis' => 'Mahrus Ali', 'kategori' => 'religi'],
            ['judul' => 'Tafsir Juz Amma', 'penulis' => 'Fahruddin Nursalam', 'kategori' => 'religi'],
            ['judul' => 'Sirah Nabawiyah Bergambar', 'penulis' => 'Abu Fathi', 'kategori' => 'religi'],
            // Pendidikan
            ['judul' => 'Bank Soal UTBK Saintek', 'penulis' => 'Tim Edu Cerdas', 'kategori' => 'pendidikan'],
            ['judul' => 'Rumus Cepat Fisika SMA', 'penulis' => 'Sri Wahyuni', 'kategori' => 'pendidikan'],
            ['judul' => 'Kamus Inggris-Indonesia Saku', 'penulis' => 'John Echols', 'kategori' => 'pendidikan'],
            ['judul' => 'Belajar Coding dengan Python', 'penulis' => 'Rizky Ramadhan', 'kategori' => 'pendidikan'],
            ['judul' => 'Atlas Indonesia Lengkap', 'penulis' => 'Tim Kartografi', 'kategori' => 'pendidikan'],
            ['judul' => 'Persiapan Tes CPNS 2026', 'penulis' => 'Bimbel Pratama', 'kategori' => 'pendidikan'],
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
                // ?default=false → 404 bila tak ada cover (tanpa itu,
                // Open Library selalu 200 + piksel kosong).
                $coverApi = "https://covers.openlibrary.org/b/isbn/{$isbn}-L.jpg?default=false";
                $response = Http::timeout(5)->get($coverApi);

                if ($response->successful()) {
                    $url = $coverApi;
                }
            } catch (Throwable) {
                // Offline / timeout — fallback di bawah.
            }

            $coverCache[$isbn] = $url;

            return $url;
        };

        $penerbitPool = [
            'Gramedia Pustaka Utama', 'Bentang Pustaka', 'Republika',
            'Gagas Media', 'Mizan', 'Erlangga', 'Andi Offset',
            'Qanita', 'Noura Books', 'Kepustakaan Populer',
        ];

        // Tanpa faker (tidak ada di image production --no-dev): acak murni PHP.
        $acak = fn (array $pool): mixed => $pool[array_rand($pool)];

        $sinopsisPool = [
            'Sebuah bacaan yang mengalir ringan namun meninggalkan kesan mendalam bagi pembacanya.',
            'Ditulis dengan bahasa yang lugas dan hangat, cocok dibaca dalam sekali duduk maupun dicicil.',
            'Setiap bab dibuka dengan kisah kecil yang mengantar pada gagasan besar di dalamnya.',
            'Cocok untuk pembaca pemula maupun yang sudah gemar membaca sejak lama.',
            'Dilengkapi contoh-contoh dekat dengan kehidupan sehari-hari pembaca Indonesia.',
            'Buku ini mengajak pembaca berhenti sejenak dan merenungkan hal-hal yang sering terlewat.',
            'Alurnya rapi, tokohnya hidup, dan penutupnya memuaskan.',
            'Bacaan yang tepat untuk menemani akhir pekan maupun perjalanan jauh.',
        ];

        $sinopsisAcak = function () use ($sinopsisPool): string {
            $pool = $sinopsisPool;
            shuffle($pool);

            return implode(' ', array_slice($pool, 0, 3));
        };

        foreach ($bookData as $index => $data) {
            // ISBN deterministik per judul — idempotent saat seed ulang.
            $isbn = '978602'.str_pad((string) (1000000 + $index * 137), 7, '0', STR_PAD_LEFT);
            $harga = random_int(35, 250) * 1000;

            // Cari buku yang sudah ada (ISBN unik) — jangan duplikat saat seed ulang.
            $book = Book::where('isbn', $isbn)->first();

            if ($book === null) {
                $stokMalang = random_int(0, 20);
                $stokSidoarjo = random_int(0, 10);

                $book = Book::create([
                    'judul' => $data['judul'],
                    'penulis' => $data['penulis'],
                    'penerbit' => $acak($penerbitPool),
                    'tahun' => random_int(2015, now()->year),
                    'isbn' => $isbn,
                    'sinopsis' => $sinopsisAcak(),
                    'harga' => $harga,
                    'stok' => 0,
                    'category_id' => $categories[$data['kategori']]->id,
                    'aktif' => true,
                    'berat_gr' => random_int(150, 900),
                    'jumlah_halaman' => random_int(96, 640),
                    'cover_url' => $coverFor($isbn)
                        ?? "https://picsum.photos/seed/{$isbn}/400/600",
                ]);

                // Cetakan ke-1 (harga beli ≈ 65% dari harga jual).
                $edition = BookEdition::create([
                    'book_id' => $book->id,
                    'cetakan_ke' => 1,
                    'harga_beli' => (int) round($harga * 0.65),
                    'harga_jual' => $harga,
                    'is_active' => true,
                ]);

                // Stok di gudang Malang & Sidoarjo.
                foreach ([['kode' => 'malang', 'qty' => $stokMalang], ['kode' => 'sidoarjo', 'qty' => $stokSidoarjo]] as $spec) {
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
                    'cover_url' => $coverFor($isbn)
                        ?? "https://picsum.photos/seed/{$isbn}/400/600",
                ]);
            }
        }

        $this->command->info('BookSeeder selesai: '.count($bookData).' buku demo tersedia.');
    }
}
