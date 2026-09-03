<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('books:clean-invalid-sku {--dry-run : Tampilkan data rusak tanpa menghapus} {--force : Hapus data rusak tanpa konfirmasi} {--fix : Perbaiki SKU menjadi urutan yang benar alih-alih menghapus}')]
#[Description('Hapus / perbaiki buku dengan kode SKU tidak sesuai singkatan kategori (mis. PRN000001 vs 001/BAI/RMP)')]
class CleanInvalidSku extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $fix = (bool) $this->option('fix');

        // Default: dry-run jika tidak ada --force dan tidak ada --fix
        if (! $dryRun && ! $force && ! $fix) {
            $dryRun = true;
        }

        if ($force && $fix) {
            $this->error('Pilih salah satu: --force (hapus) atau --fix (perbaiki), jangan keduanya.');

            return self::FAILURE;
        }

        $this->info('Memeriksa buku dengan SKU rusak...');

        $invalid = $this->findInvalidBooks();

        if ($invalid->isEmpty()) {
            $this->info('Tidak ada data rusak. Semua SKU sudah sesuai kategori dan berformat {KODE}{6 digit}.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Ditemukan %d buku dengan SKU rusak:', $invalid->count()));
        $this->table(
            ['ID', 'Judul', 'SKU Lama', 'Kategori', 'SKU Seharusnya', 'Status'],
            $invalid->map(fn (array $row): array => [
                substr($row['id'], 0, 8).'…',
                str($row['judul'])->limit(35),
                $row['kode_sku'] ?? '—',
                $row['kategori'] ?? '—',
                $row['sku_baru'] ?? '—',
                $row['alasan'],
            ])->all(),
        );

        if ($dryRun) {
            $this->info('Mode dry-run: tidak ada data yang dihapus/diubah.');
            $this->line('Jalankan dengan --fix untuk memperbaiki SKU, atau --force untuk menghapus (soft-delete).');
            $this->line('Contoh: php artisan books:clean-invalid-sku --fix');
            $this->line('Contoh: php artisan books:clean-invalid-sku --force');

            return self::SUCCESS;
        }

        if ($fix) {
            return $this->fixInvalid($invalid);
        }

        return $this->deleteInvalid($invalid, $force);
    }

    /**
     * Cari buku dengan SKU tidak valid.
     *
     * @return Collection<int, array{id: string, judul: string, kode_sku: string|null, kategori: string|null, kategori_kode: string|null, sku_baru: string|null, alasan: string}>
     */
    private function findInvalidBooks(): Collection
    {
        $books = Book::with(['category:id,kode,nama'])->withTrashed()->get();
        $result = collect();

        // Untuk simulasi SKU baru yang urut (tanpa menimpa yang valid)
        $usedNumbersByCat = [];
        $usedSkus = $books->pluck('kode_sku')->filter()->flip()->toArray();

        // Kumpulkan nomor yang sudah valid per kategori
        foreach ($books as $book) {
            $kode = $book->category?->kode;
            if ($kode === null || $book->kode_sku === null) {
                continue;
            }
            if ($this->isValidSku($book->kode_sku, $kode)) {
                $num = (int) substr($book->kode_sku, strlen($kode));
                $usedNumbersByCat[$kode][$num] = true;
            }
        }

        // Pre-mark SKU valid yang akan dipakai (agar alokasi celah tidak nabrak)
        // Tidak perlu, karena kita hanya simulasi untuk tampilan sku_baru

        foreach ($books as $book) {
            $kode = $book->category?->kode;
            $sku = $book->kode_sku;
            $alasan = null;
            $skuBaru = null;

            if ($book->category === null) {
                if ($sku !== null) {
                    $alasan = 'Tanpa kategori tapi punya SKU';
                } else {
                    continue; // tanpa kategori & tanpa SKU bukan rusak
                }
            } elseif ($kode === null) {
                // Kategori tanpa kode (mis. Fiksi demo) — tidak bisa validasi SKU
                continue;
            } elseif ($sku === null || trim($sku) === '') {
                $alasan = 'SKU kosong';
                $skuBaru = $this->previewNextSku($kode, $usedNumbersByCat, $usedSkus);
            } elseif (! $this->isValidSku($sku, $kode)) {
                $alasan = sprintf('SKU "%s" tidak match kategori "%s"', $sku, $kode);
                $skuBaru = $this->previewNextSku($kode, $usedNumbersByCat, $usedSkus);
            } else {
                continue; // valid
            }

            $result->push([
                'id' => $book->id,
                'judul' => $book->judul,
                'kode_sku' => $sku,
                'kategori' => $book->category?->nama.' ('.$kode.')',
                'kategori_kode' => $kode,
                'sku_baru' => $skuBaru,
                'alasan' => $alasan,
            ]);

            // Simulasi alokasi agar sku_baru berikutnya tidak duplikat di preview
            if ($skuBaru !== null) {
                $usedSkus[$skuBaru] = true;
                $num = (int) substr($skuBaru, strlen($kode));
                $usedNumbersByCat[$kode][$num] = true;
            }
        }

        return $result;
    }

    private function previewNextSku(string $kode, array &$usedNumbersByCat, array &$usedSkus): string
    {
        $usedNumbers = $usedNumbersByCat[$kode] ?? [];

        for ($n = 1; $n <= 999999; $n++) {
            if (isset($usedNumbers[$n])) {
                continue;
            }
            $candidate = $kode.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
            if (isset($usedSkus[$candidate])) {
                continue;
            }

            return $candidate;
        }

        return $kode.'999999';
    }

    private function isValidSku(string $sku, string $kode): bool
    {
        return preg_match('/^'.preg_quote($kode, '/').'\d{6}$/', $sku) === 1;
    }

    private function fixInvalid(Collection $invalid): int
    {
        $this->info('Memperbaiki SKU...');

        $fixed = 0;
        $usedSkus = Book::withTrashed()->whereNotNull('kode_sku')->pluck('kode_sku')->flip()->toArray();
        $usedNumbersByCat = [];

        // Build used numbers dari DB saat ini
        foreach (Book::withTrashed()->with('category')->get() as $book) {
            $kode = $book->category?->kode;
            if ($kode === null || $book->kode_sku === null) {
                continue;
            }
            if ($this->isValidSku($book->kode_sku, $kode)) {
                $num = (int) substr($book->kode_sku, strlen($kode));
                $usedNumbersByCat[$kode][$num] = true;
            }
        }

        foreach ($invalid as $row) {
            $book = Book::withTrashed()->find($row['id']);
            if ($book === null) {
                continue;
            }

            $kode = $row['kategori_kode'];
            if ($kode === null) {
                continue;
            }

            // Cari SKU baru yang valid & urut
            $usedNumbers = $usedNumbersByCat[$kode] ?? [];
            $newSku = null;
            for ($n = 1; $n <= 999999; $n++) {
                if (isset($usedNumbers[$n])) {
                    continue;
                }
                $candidate = $kode.str_pad((string) $n, 6, '0', STR_PAD_LEFT);
                if (isset($usedSkus[$candidate])) {
                    $usedNumbers[$n] = true;
                    $usedNumbersByCat[$kode] = $usedNumbers;

                    continue;
                }
                $newSku = $candidate;
                $usedNumbers[$n] = true;
                $usedNumbersByCat[$kode] = $usedNumbers;
                $usedSkus[$candidate] = true;
                // Hapus SKU lama dari usedSkus agar tidak dianggap terpakai lagi
                if ($book->kode_sku !== null) {
                    unset($usedSkus[$book->kode_sku]);
                }
                break;
            }

            if ($newSku === null) {
                $this->error("Gagal alokasi SKU untuk {$book->judul}");

                continue;
            }

            $old = $book->kode_sku;
            $book->update(['kode_sku' => $newSku]);
            $this->line(sprintf('  ✓ %s: %s → %s', str($book->judul)->limit(30), $old ?? '—', $newSku));
            $fixed++;
        }

        $this->info("Selesai. {$fixed} buku diperbaiki.");

        return self::SUCCESS;
    }

    private function deleteInvalid(Collection $invalid, bool $force): int
    {
        if (! $force) {
            if (! $this->confirm(sprintf('Yakin hapus %d buku rusak? (soft-delete, bisa restore)', $invalid->count()), false)) {
                $this->info('Dibatalkan.');

                return self::SUCCESS;
            }
        }

        $count = 0;
        foreach ($invalid as $row) {
            $book = Book::find($row['id']); // hanya yang belum terhapus
            if ($book === null) {
                continue;
            }

            // Jangan hapus jika punya riwayat pesanan — soft-delete diblokir di controller, tapi di sini kita cek
            if ($book->hasOrderHistory()) {
                $this->warn(sprintf('  ! Skip %s (%s) — punya riwayat pesanan', str($book->judul)->limit(30), $book->kode_sku));

                continue;
            }

            $book->delete();
            $this->line(sprintf('  × Hapus %s (%s)', str($book->judul)->limit(30), $row['kode_sku'] ?? '—'));
            $count++;
        }

        $this->info("Selesai. {$count} buku dihapus (soft-delete).");
        $this->line('Untuk restore: php artisan tinker --execute "Book::withTrashed()->where(...)->restore()"');

        return self::SUCCESS;
    }
}
