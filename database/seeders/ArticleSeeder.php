<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Artikel konten demo storefront editorial — kategori + artikel terbit.
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

            return Article::factory()->create([
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
                'is_featured' => $featured,
                'published_at' => now()->subDays($publishedAfterDays)->toDateString(),
                'created_at' => now()->subDays(abs($day)),
                'updated_at' => now()->subDays(abs($day)),
            ]);
        };

        // Artikel unggulan (hero beranda) — is_featured = true.
        $create(
            'Sikap Seorang Muslim terhadap Waktu: Antara Amanah dan Kesempatan',
            'Biografi',
            'Waktu adalah amanah yang paling sering kita sia-siakan tanpa sadar. Bagaimana Rasulullah dan para sahabat memaknai setiap detik, dan apa yang bisa kita tiru.',
            [
                'Ada satu aset yang diberikan sama rata kepada seluruh manusia, tanpa peduli pangkat, usia, atau kekayaan: waktu. Namun meski sama-sama dua puluh empat jam, cara setiap orang mengisinya sangat berbeda.',
                'Para sahabat memahami waktu sebagai ibadah. Mereka tidak sekadar "mengisi" hari, melainkan menegosiasikan setiap menitnya dengan ilmu dan amal. Umar bin Khattab bahkan dikenal menjauhi pergunjingan karena tahu lisannya terhitung.',
                'Maka pertanyaan yang lebih jujur bukanlah "seberapa sibuk Anda?", melainkan "ke mana perginya waktu yang dulu Anda inginkan untuk hal-hal penting?".',
                'Menutup buku ini, cobalah satu hal: tuliskan satu hal penting hari ini, lalu berikan padanya waktu terbaik Anda — bukan sisanya.',
            ],
            'quote',
            1,
            'Ustadz Ahmad Fauzi',
            featured: true,
        );

        // Artikel terbaru (section "Artikel Terbaru" — 5 terbaru upload).
        $create(
            'Ikhlas: Beban yang Menjadi Ringan',
            'Akhlak',
            'Ikhlas sering terdengar mudah, padahal ia medan yang paling berat. Sebuah renungan tentang menyeimbangkan amal dan hati.',
            [
                'Dalam amal, ada dua pelaku: tangan yang mengerjakan dan hati yang menilai. Yang pertama bisa dilatih, yang kedua justru sering menjadi penghalang terbesar.',
                'Ikhlas bukan berarti tidak ingin dihargai; ia berarti tidak menjadikan penghargaan itu sebagai tujuan. Siapa yang ikhlas tidak lagi bertanya "seberapa besar balasannya?" tapi "apakah ini yang terbaik yang bisa aku lakukan?".',
                'Sebagian ulama menyebut ikhlas sebagai rahasia antara hamba dan Rabb-nya — malaikat tidak mencatatnya, setan tidak mengetahuinya, dan kadang hamba sendiri baru menyadarinya belakangan.',
            ],
            'shelf',
            2,
        );
        $create(
            'Puasa dan Sabar: Dua Sisi Satu Koin',
            'Akidah',
            'Puasa melatih fisik, sabar melatih jiwa. Keduanya adalah koin yang sama — dan keduanya dibutuhkan di bulan-bulan biasa, bukan hanya bulan istimewa.',
            [
                'Ketika rasa lapar dan haus menyapa, tubuh berteriak sementara jiwa dituntut diam. Di situlah puasa mengajarkan sesuatu yang tidak diajarkan oleh buku mana pun.',
                'Sabar bukanlah menahan tanpa arah; ia adalah menahan sambil tetap berbuat. Inilah yang membedakan orang yang pasrah dari orang yang berserah.',
            ],
            'stack',
            4,
            'Ustadzah Nur Hidayah',
        );
        $create(
            'Bersuci: Awal yang Sepele, Akhir yang Dalam',
            'Fikih & Muamalah',
            'Thaharah sering dianggap sekadar tata-cara, padahal ia tarbiyah: melatih ketertiban, kebersihan, dan kesadaran bahwa ibadah dimulai dari fisik yang suci.',
            [
                'Dalam fikih, bersuci (thaharah) adalah kunci banyak ibadah. Namun di balik tata-caranya, ia menyimpan pesan yang lebih luas: agama ini menempatkan kebersihan sebagai bagian dari keimanan.',
                'Ketika seseorang terbiasa menjaga kesucian air, tempat, dan pakaiannya, ia pelan-pelan terbiasa menjaga yang lebih sulit — hatinya.',
            ],
            'lamp',
            6,
        );
        $create(
            'Memaknai Doa di Tengah Kesibukan',
            'Doa & Zikir',
            'Doa bukan sekadar permintaan di waktu sulit; ia dialog harian yang menjernihkan arah hidup di tengah rutinitas.',
            [
                'Kita cenderung mengasosiasikan doa dengan air mata dan kesulitan. Padahal doa yang paling jujur justru lahir di hari-hari biasa — ketika tidak ada hal mendesak yang memintanya.',
                'Doa adalah jeda. Ia memaksa kita berhenti sejenak dari daftar tugas, lalu menegaskan kembali kepada siapa kita sungguh-sungguh berharap.',
            ],
            'pencil',
            9,
            'Redaksi',
        );
        $create(
            'Berinteraksi dengan Al-Qur’an: Bukan Sekadar Membaca',
            'Al-Qur’an',
            'Tilawah bukan ukuran kecepatan melafalkan, melainkan kedalaman meresapi. Bagaimana menjadikan Al-Qur’an sahabat harian yang hidup.',
            [
                'Membaca Al-Qur’an dengan tartil adalah perintah, tetapi maknanya justru menjadi lengkap ketika ia mengubah tindakan. Ayat yang dibaca dengan hati menuntun langkah, bukan hanya mengisi bibir.',
                'Yang membedakan pembaca biasa dari pezikir yang berinteraksi adalah kesediaannya berhenti: berhenti pada ayat yang menusuk, lalu merenungkan apa yang harus diubah.',
            ],
            'manuscript',
            12,
            'Ustadz Abdullah',
        );

        // Artikel lain untuk mengisi feed "Semua Artikel".
        $create(
            'Sisi Lain Sejarah Islam: Ketika Peradaban Bertemu Ilmu',
            'Sejarah',
            'Islam pernah menjadi penerjemah dan penjaga ilmu dunia. Sebuah catatan betapa masjid, bukan perpustakaan, yang pertama menjadi rumah bagi sains.',
            [
                'Di Baghdad dan Cordoba, para ilmuwan muslim tidak sekadar menghafal warisan Yunani; mereka membedah, menguji, dan memperbaiki. Terjemahan bukan akhir — ia titik tolak penelitian baru.',
                'Masjid menjadi ruang belajar: fikih, tata bahasa, matematika, dan astronomi diajarkan berdampingan. Ilmu tidak dipandang sekuler atau sakral, melainkan satu — sama-sama nikmat.',
                'Kisah ini mengingatkan bahwa umat beradab bukan yang paling banyak buku, melainkan yang paling berani menguji gagasan dengan akal dan adab.',
            ],
            'manuscript',
            15,
            'Redaksi',
        );
        $create(
            'Menjaga Lisan: Ibadah yang Paling Sering Terlupakan',
            'Akhlak',
            'Lisan adalah anggota tubuh yang paling ringan menggerakkannya dan paling berat pertanggungjawabannya. Tentang bagaimana menjaga kata-kata.',
            [
                'Dari sekian banyak ibadah, menjaga lisan mungkin yang paling sulit dideteksi orang lain. Tidak ada saf yang rapi atau jamaah yang tepuk tangan. Hanya hati yang tahu.',
                'Kata-kata, sekali keluar, tidak bisa dikembalikan. Maka jeda sebelum bicara bukan masalah sopan santun belaka — ia latihan kehadiran dan tanggung jawab.',
            ],
            'quote',
            18,
            'Ustadzah Siti Aminah',
        );
        $create(
            'Kaya yang Hakiki: Catatan tentang Qanaah',
            'Akidah',
            'Di tengah budaya konsumsi, qanaah terdengar kuno. Padahal ia justru jawaban modern atas kecemasan yang tidak pernah puas.',
            [
                'Budaya kita mengukur cukup dengan lebih: genset terbaru, ruangan lebih lebar, notifikasi lebih banyak. Qanaah mengukur sebaliknya — seberapa sedikit yang dibutuhkan agar hati tenang.',
                'Qanaah bukan kemalasan atau penolakan terhadap rezeki. Ia keputusan sadar untuk tidak menjadikan barang sebagai ukuran nilai diri.',
                'Orang yang qanaah tidak berarti berhenti berusaha; ia hanya berhenti membandingkan. Dan itu membebaskan.',
            ],
            'shelf',
            21,
            'Ustadz Ahmad Fauzi',
        );

        // Bersihkan sisa duplikat soft-deleted (judul sama) agar DB benar-benar bersih
        $activeJuduls = Article::pluck('judul');
        $trashedDups = Article::onlyTrashed()->whereIn('judul', $activeJuduls)->count();

        if ($trashedDups > 0) {
            Article::onlyTrashed()->whereIn('judul', $activeJuduls)->forceDelete();
        }

        $this->command->info('Artikel demo selesai: '.collect($categories)->count()." kategori, {$created} baru, {$skipped} sudah ada (1 unggulan).".($trashedDups > 0 ? " {$trashedDups} duplikat soft-deleted dibersihkan." : ''));
    }
}
