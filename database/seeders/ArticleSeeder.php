<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Artikel konten demo storefront editorial — kategori + artikel terbit.
 *
 * Konten acak bertema buku & literasi agar beranda terlihat hidup
 * setelah reset data.
 *
 * Idempoten: kategori dan artikel dicek via firstOrCreate / where judul
 * untuk mencegah duplikat saat seeder dipanggil berulang. Hanya 1 artikel
 * unggulan (is_featured) yang aktif — observer memastikan single flag.
 *
 * Pemakaian:
 *   php artisan db:seed --class=ArticleSeeder
 */
class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        // Kategori konten (sesuai desain editorial Islami).
        $categories = collect([
            'Akidah', 'Fikih & Muamalah', 'Akhlak', 'Hadis',
            'Al-Qur’an', 'Doa & Zikir', 'Sejarah', 'Biografi',
        ])->mapWithKeys(fn (string $nama): array => [
            $nama => ArticleCategory::firstOrCreate(
                ['slug' => Str::slug($nama)],
                ['nama' => $nama],
            ),
        ]);

        $day = 0;

        $created = 0;
        $skipped = 0;

        $create = function (
            string $judul,
            string $kategori,
            string $ringkasan,
            array $paragraf,
            string $motif,
            int $publishedAfterDays,
            string $penulis = 'Redaksi',
            bool $featured = false,
        ) use ($categories, &$day, &$created, &$skipped): Article {
            $day--;

            // Cek duplikat by judul (demo data judul unik) — skip jika sudah ada
            $existing = Article::withTrashed()->where('judul', $judul)->first();

            if ($existing) {
                // Soft-deleted duplikat historis → restore sekalian bersihkan
                if ($existing->trashed()) {
                    $existing->restore();
                }

                $skipped++;

                return $existing;
            }

            $created++;

            // Tanpa factory (butuh faker yg tak ada di image production):
            // create langsung — default DB menutupi sisanya.
            return Article::create([
                'judul' => $judul,
                'slug' => Str::slug($judul),
                'article_category_id' => $categories[$kategori]->id,
                'penulis' => $penulis,
                'ringkasan' => $ringkasan,
                'isi' => implode("\n", array_map(
                    fn (string $p): string => '<p>'.$p.'</p>',
                    $paragraf,
                )),
                'motif' => $motif,
                'is_active' => true,
                'is_featured' => $featured,
                'published_at' => now()->subDays($publishedAfterDays)->toDateString(),
                'created_at' => now()->subDays(abs($day)),
                'updated_at' => now()->subDays(abs($day)),
            ]);
        };

        $penulisPool = ['Redaksi', 'Tim Toko Buku', 'Ahmad Fauzi', 'Nur Hidayah', 'Siti Aminah'];
        // Tanpa faker (tidak ada di image production --no-dev): acak murni PHP.
        $penulis = fn (): string => $penulisPool[array_rand($penulisPool)];

        $artikel = [
            [
                'Membaca 10 Menit Sehari: Kebiasaan Kecil Dampak Besar',
                'Akhlak',
                'Tidak perlu maraton membaca — sepuluh menit konsisten setiap hari menuntaskan belasan buku dalam setahun.',
                [
                    'Banyak orang gagal membangun kebiasaan membaca karena memasang target terlalu besar: lima puluh halaman sehari, satu buku seminggu. Target besar melelahkan sebelum menjadi kebiasaan.',
                    'Mulailah dari sepuluh menit — seusai subuh, saat menunggu antrean, atau sebelum tidur. Sepuluh menit sehari sama dengan enam puluh jam setahun: cukup untuk belasan buku.',
                    'Kuncinya bukan durasi, melainkan keberulangan. Letakkan buku di tempat yang terlihat, jauhkan gawai, dan biarkan kebiasaan kecil ini bekerja diam-diam.',
                ],
                true,
            ],
            [
                'Cara Memilih Buku Anak Sesuai Usia',
                'Biografi',
                'Buku yang tepat di usia yang tepat menumbuhkan cinta baca; buku yang terlalu sulit justru mematikannya.',
                [
                    'Untuk balita, pilih buku bergambar besar dengan sedikit teks dan bahan yang tahan dibanting. Isinya sederhana: warna, hewan, dan rutinitas harian.',
                    'Anak usia sekolah dasar mulai menikmati cerita berpetualang dengan tokoh seusianya. Biarkan mereka memilih sendiri — rasa memiliki memperbesar minat membaca.',
                    'Remaja membutuhkan cerita yang menghargai kecerdasannya: konflik nyata, tokoh kompleks, dan tema yang berani. Dampingi, jangan sensor membabi buta.',
                ],
                false,
            ],
            [
                'Merawat Buku Agar Awet Bertahun-tahun',
                'Sejarah',
                'Buku yang dirawat bisa diwariskan. Beberapa kebiasaan sederhana menjaganya tetap kokoh puluhan tahun.',
                [
                    'Musuh utama buku adalah lembap, sinar matahari langsung, dan cara membuka yang kasar. Simpan rak di ruangan berventilasi dan jauh dari jendela.',
                    'Jangan melipat sudut halaman sebagai penanda — gunakan pembatas buku. Saat membuka buku tebal, sangga punggungnya agar jilidan tidak retak.',
                    'Buku yang jarang dibaca pun perlu "diangin-anginkan" sesekali: buka halamannya, kibaskan debunya, dan periksa dari rayap.',
                ],
                false,
            ],
            [
                'Membangun Perpustakaan Keluarga dengan Budget Pas-pasan',
                'Akidah',
                'Perpustakaan keluarga tidak harus besar — yang penting bertumbuh. Mulai dari satu rak dan satu buku per bulan.',
                [
                    'Satu buku per bulan berarti dua belas buku setahun. Dalam lima tahun, rak Anda berisi enam puluh judul pilihan — warisan yang tak ternilai.',
                    'Manfaatkan bazar buku, lapak buku bekas, dan tukar-tambah dengan tetangga. Buku bekas yang baik isinya sama berharganya dengan buku baru.',
                    'Libatkan anak dalam memilih dan merawat koleksi. Anak yang ikut membangun perpustakaan akan ikut menjaganya.',
                ],
                false,
            ],
            [
                'Bedah Buku: Mengapa Cerita Fiksi Melatih Empati',
                'Biografi',
                'Penelitian menunjukkan pembaca fiksi lebih peka membaca emosi orang lain. Fiksi adalah simulator kehidupan sosial.',
                [
                    'Saat mengikuti tokoh fiksi, otak berlatih menebak motif, merasakan dilema, dan memahami sudut pandang yang asing. Itu latihan empati yang paling menyenangkan.',
                    'Tak heran profesi yang banyak berurusan dengan manusia — guru, orang tua, pemimpin — sering kali adalah pembaca fiksi yang rakus.',
                    'Jadi lain kali seseorang meremehkan novel sebagai "bacaan ringan", ingatlah: ringan di tangan, berat manfaatnya.',
                ],
                false,
            ],
            [
                'Tips Fokus Membaca di Tengah Gempuran Notifikasi',
                'Akhlak',
                'Perhatian adalah mata uang era digital. Membaca menuntutnya kembali — begini cara merebutnya.',
                [
                    'Aktifkan mode jangan-ganggu dan letakkan gawai di ruangan lain. Godaan terbesar bukan notifikasi yang masuk, melainkan kebiasaan mengecek tanpa sebab.',
                    'Tentukan sesi membaca yang pendek tapi utuh: dua puluh lima menit tanpa jeda, lalu istirahat lima menit. Ulangi dua-tiga putaran.',
                    'Catat satu hal menarik dari tiap sesi di buku catatan. Catatan kecil mengubah membaca pasif menjadi dialog aktif.',
                ],
                false,
            ],
            [
                'Dari Rak Toko ke Hati Pembaca: Kurasi Bulan Ini',
                'Sejarah',
                'Tim kami memilih lima judul yang paling sering ditanyakan pembeli bulan ini — dari fiksi hingga panduan praktis.',
                [
                    'Setiap bulan, rak kami kedatangan puluhan judul baru. Tidak semuanya sempat kami baca, tetapi semuanya kami saring berdasarkan kualitas isi dan kebutuhan pembaca.',
                    'Bulan ini, tema yang paling dicari adalah pengembangan diri dan literatur anak. Para orang tua berburu buku penunjang belajar sekaligus bacaan pengantar tidur.',
                    'Datang dan tanyakan pada penjaga toko — kami senang merekomendasikan buku yang benar-benar cocok, bukan yang sekadar laris.',
                ],
                false,
            ],
            [
                'Membaca Nyaring untuk Anak: Manfaat yang Jarang Disadari',
                'Doa & Zikir',
                'Membacakan buku dengan suara lantang memperkaya kosakata anak jauh lebih cepat daripada percakapan biasa.',
                [
                    'Bahasa buku lebih kaya daripada bahasa sehari-hari. Anak yang rutin dibacakan buku mendengar ribuan kata baru setiap tahun — modal besar untuk sekolah.',
                    'Momen membacakan juga adalah momen kedekatan: pangkuan orang tua, suara yang hangat, dan cerita yang dinanti. Anak mengingat rasanya, bukan hanya isinya.',
                    'Tidak perlu lama — lima belas menit sebelum tidur cukup. Konsistensi mengalahkan durasi.',
                ],
                false,
            ],
            [
                'Jurnal Bacaan: Cara Sederhana Mengingat Isi Buku',
                'Fikih & Muamalah',
                'Kita lupa sembilan puluh persen isi buku dalam sebulan. Jurnal bacaan satu halaman menyelamatkan sisanya.',
                [
                    'Setelah menutup buku, tulis tiga hal: satu gagasan utama, satu kutipan favorit, dan satu tindakan yang akan dilakukan. Cukup lima menit.',
                    'Jurnal ini menjadi peta harta karun pribadi. Setahun kemudian, membacanya kembali sama menyenangkannya dengan membaca bukunya.',
                    'Tidak perlu buku khusus — halaman belakang buku itu sendiri atau aplikasi catatan di gawai sudah cukup.',
                ],
                false,
            ],
            [
                'Buku Bekas vs Buku Baru: Mana yang Lebih Bijak?',
                'Hadis',
                'Keduanya punya tempat. Kuncinya tahu kapan menghemat dan kapan berinvestasi.',
                [
                    'Buku referensi yang sering dibuka — kamus, tafsir, buku pelajaran — layak dibeli baru karena akan dipakai bertahun-tahun.',
                    'Novel sekali baca dan buku anak yang cepat "lulus" sangat cocok dibeli bekas atau dipinjam. Isinya sama, harganya separuh.',
                    'Apapun pilihannya, yang terpenting bukunya dibaca. Rak penuh buku tak tersentuh lebih mubazir daripada satu buku lusuh yang habis dibaca.',
                ],
                false,
            ],
            [
                'Mengenalkan Sejarah Islam Lewat Dongeng',
                'Al-Qur’an',
                'Anak menyerap sejarah paling baik lewat cerita. Dongeng para nabi dan sahabat adalah gerbangnya.',
                [
                    'Fakta dan tahun membosankan bagi anak, tetapi kisah keberanian, kejujuran, dan pengorbanan menempel di ingatan mereka.',
                    'Mulailah dari kisah yang dekat dengan keseharian: kejujuran Nabi Muhammad berdagang, keberanian Ali kecil, atau kedermawanan Utsman.',
                    'Ceritakan dengan ekspresi dan jeda dramatis. Anak yang terpukau malam ini akan meminta lagi besok malam.',
                ],
                false,
            ],
            [
                'Rutinitas Pagi Pecinta Buku',
                'Doa & Zikir',
                'Secangkir teh, cahaya matahari pagi, dan dua puluh halaman — resep memulai hari dengan tenang.',
                [
                    'Pagi adalah waktu perhatian paling segar. Membaca sebelum gawai mengambil alih menentukan nada seluruh hari.',
                    'Tidak harus buku berat — bacaan ringan yang menggugah pun cukup. Yang penting pikiran diajak berpikir sebelum diajak bereaksi.',
                    'Cobalah seminggu. Banyak pembaca melaporkan hari yang lebih tenang hanya dari kebiasaan kecil ini.',
                ],
                false,
            ],
            [
                'Hadiah Buku: Selalu Tepat untuk Siapa Saja',
                'Akhlak',
                'Bingung memilih kado? Buku hampir tidak pernah salah — asal dipilih dengan sedikit perhatian.',
                [
                    'Hadiah terbaik menunjukkan perhatian: buku tentang hobi penerimanya, penulis favoritnya, atau fase hidup yang sedang dijalani.',
                    'Tuliskan pesan singkat di halaman pertama. Bertahun-tahun kemudian, pesan itulah yang paling dikenang — bahkan melebihi bukunya.',
                    'Untuk anak, hadiahkan buku sedikit di atas levelnya sekarang. Buku yang "menantang" tumbuh bersama pembacanya.',
                ],
                false,
            ],
            [
                'Komunitas Baca: Membaca Jadi Lebih Seru Bareng',
                'Sejarah',
                'Membaca terasa sepi? Klub buku mengubah hobi soliter menjadi petualangan kolektif.',
                [
                    'Mendiskusikan buku memaksa kita membaca lebih saksama — memperhatikan detail yang luput saat membaca sendirian.',
                    'Tidak perlu formal: tiga teman, satu judul per bulan, dan obrolan santai sudah cukup disebut komunitas baca.',
                    'Mulai dari lingkungan terdekat: keluarga, masjid, atau kantor. Satu orang memulai, yang lain biasanya menyusul.',
                ],
                false,
            ],
        ];

        $motifPool = ['quote', 'shelf', 'stack', 'lamp', 'manuscript', 'pencil'];

        foreach ($artikel as $index => [$judul, $kategori, $ringkasan, $paragraf, $featured]) {
            $create(
                $judul,
                $kategori,
                $ringkasan,
                $paragraf,
                $motifPool[array_rand($motifPool)],
                ($index + 1) * 3,
                $penulis(),
                $featured,
            );
        }

        // Bersihkan sisa duplikat soft-deleted (judul sama) agar DB benar-benar bersih
        $activeJuduls = Article::pluck('judul');
        $trashedDups = Article::onlyTrashed()->whereIn('judul', $activeJuduls)->count();

        if ($trashedDups > 0) {
            Article::onlyTrashed()->whereIn('judul', $activeJuduls)->forceDelete();
        }

        $this->command->info('Artikel demo selesai: '.collect($categories)->count()." kategori, {$created} baru, {$skipped} sudah ada (1 unggulan).".($trashedDups > 0 ? " {$trashedDups} duplikat soft-deleted dibersihkan." : ''));
    }
}
