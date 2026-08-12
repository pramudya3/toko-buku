<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Split INVOICE - PRICELIST.csv menjadi 4 file import:
 * kategori.csv, buku.csv, promo.csv (bundle), tier-discount.csv.
 */
final class SplitPricelist extends Command
{
    protected $signature = 'pricelist:split
        {--file=INVOICE - PRICELIST.csv : File sumber CSV}
        {--out=storage/app/imports : Folder output}';

    protected $description = 'Pisahkan INVOICE - PRICELIST.csv menjadi CSV per domain (kategori/buku/promo/tier discount)';

    /** @var array<int, array{line: int, cells: array<int, string>}> */
    private array $rows = [];

    public function handle(): int
    {
        $path = base_path($this->option('file'));
        $out = $this->option('out');

        if (! is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $this->rows = $this->parseCsv($path);

        $kiri = [];
        $kanan = [];

        foreach ($this->rows as $row) {
            $c = $row['cells'];

            if ($c[1] !== '') {
                $kiri[] = [
                    'judul' => $c[1],
                    'harga_normal' => $this->parseHarga($c[2]),
                    'harga_promo_guru' => $this->parseHarga($c[3]),
                    'pct' => $c[4],
                    'potongan' => $c[5],
                ];
            }

            if ($c[11] !== '') {
                $kanan[] = [
                    'kategori' => $c[9],
                    'kode' => $c[10],
                    'judul' => $c[11],
                    'penulis' => $c[12],
                    'harga_jual' => $this->parseHarga($c[13]),
                ];
            }
        }

        $this->info('KIRI: '.count($kiri).' baris | KANAN: '.count($kanan).' baris');

        // KANAN non-bundling dipakai untuk match harga beli; baris bundling
        // hanya untuk promo.
        $kananNonBundling = array_values(array_filter(
            $kanan,
            fn (array $row): bool => $this->norm($row['kategori']) !== 'bundling',
        ));

        // ── Match KIRI → KANAN (buku sama, beda ejaan) → harga beli ──
        $hargaBeli = []; // norm judul KANAN => harga normal KIRI
        $kiriUnik = [];

        foreach ($kiri as $row) {
            if (Str::startsWith($this->norm($row['judul']), 'bundling')) {
                continue; // baris bundling tidak masuk buku
            }

            $match = $this->findMatch($row['judul'], $kananNonBundling);

            if ($match !== null) {
                $hargaBeli[$this->norm($match['judul'])] = $row['harga_normal'];
            } else {
                $kiriUnik[] = $row;
            }
        }

        $this->info('Match KIRI→KANAN: '.count($hargaBeli).' | KIRI unik: '.count($kiriUnik));

        // ── Kategori (tanpa Bundling) ──
        $categories = [];

        foreach ($kanan as $row) {
            if ($row['kategori'] === '' || $this->norm($row['kategori']) === 'bundling') {
                continue;
            }

            $key = $this->norm($row['kategori']);
            $kode = $this->kodePrefix($row['kode']);

            if ($kode === '') {
                continue; // kode diambil dari baris lain yang valid (mis. PRN)
            }

            $categories[$key] ??= ['kode' => $kode, 'nama' => $row['kategori']];
        }

        ksort($categories);

        // ── Buku: KANAN non-bundling + KIRI unik ──
        $books = [];

        foreach ($kanan as $row) {
            if ($this->norm($row['kategori']) === 'bundling') {
                continue;
            }

            $books[] = [
                'kategori' => $row['kategori'],
                'kode' => $row['kode'],
                'judul' => $row['judul'],
                'penulis' => $row['penulis'],
                'harga_jual' => (string) ($row['harga_jual'] ?? ''),
                'harga_beli' => (string) ($hargaBeli[$this->norm($row['judul'])] ?? ''),
            ];
        }

        foreach ($kiriUnik as $row) {
            $books[] = [
                'kategori' => '',
                'kode' => '',
                'judul' => $row['judul'],
                'penulis' => '',
                'harga_jual' => (string) ($row['harga_normal'] ?? ''),
                'harga_beli' => (string) ($row['harga_normal'] ?? ''),
            ];
        }

        $judulMap = []; // norm judul => judul asli + harga jual

        foreach ($books as $book) {
            $judulMap[$this->norm($book['judul'])] = [
                'judul' => $book['judul'],
                'harga' => (int) $book['harga_jual'],
            ];
        }

        // ── Promo bundle: KANAN bundling + KIRI bundling (dedupe fuzzy+harga) ──
        $promos = [];

        foreach ($kanan as $row) {
            if ($this->norm($row['kategori']) !== 'bundling') {
                continue;
            }

            $promo = $this->buildPromo($row['judul'], $row['harga_jual'], $judulMap);
            $promos[$this->norm($row['judul'])] = $promo;
        }

        foreach ($kiri as $row) {
            if (! Str::startsWith($this->norm($row['judul']), 'bundling')) {
                continue;
            }

            $key = $this->norm($row['judul']);
            $dupe = $this->findPromoDupe($key, $promos, $row['harga_normal']);

            if ($dupe !== null) {
                // Duplikat KIRI: pakai qty yang lebih besar (mis. "(3 buku)").
                $kiriQty = $this->bundleQty($row['judul']);

                if ($kiriQty > (int) $promos[$dupe]['qty']) {
                    $promos[$dupe] = $this->buildPromo(
                        $promos[$dupe]['promo_name'],
                        $row['harga_normal'],
                        $judulMap,
                        $kiriQty,
                    );
                }

                continue;
            }

            $promos[$key] = $this->buildPromo($row['judul'], $row['harga_normal'], $judulMap);
        }

        // ── Tier discount (global, min_qty 1) ──
        $tierDiscounts = [
            ['tier' => 'guru', 'min_qty' => '1', 'discount_percent' => (string) $this->guruDiscountMode($kiri)],
            ['tier' => 'bazaf', 'min_qty' => '1', 'discount_percent' => '10'],
        ];

        // ── Tulis file ──
        $this->ensureDir($out);

        $this->writeCsv("{$out}/kategori.csv", ['kode', 'nama'], array_values($categories));
        $this->writeCsv("{$out}/buku.csv", ['kategori', 'kode', 'judul', 'penulis', 'harga_jual', 'harga_beli'], $books);
        $this->writeCsv(
            "{$out}/promo.csv",
            ['promo_name', 'promo_type', 'discount_percent', 'komponen', 'catatan'],
            array_values($promos),
        );
        $this->writeCsv("{$out}/tier-discount.csv", ['tier', 'min_qty', 'discount_percent'], $tierDiscounts);

        $this->info('kategori.csv: '.count($categories).' | buku.csv: '.count($books).' | promo.csv: '.count($promos).' | tier-discount.csv: '.count($tierDiscounts));

        return self::SUCCESS;
    }

    /**
     * Bangun baris promo dari nama bundle: resolve komponen + diskon.
     *
     * @param  array<string, array{judul: string, harga: int}>  $judulMap
     * @return array<string, string>
     */
    private function buildPromo(string $nama, ?int $hargaBundle, array $judulMap, int $qtyOverride = 0): array
    {
        $komponen = $this->resolveComponents($nama, $judulMap, $qtyOverride);
        $qty = $qtyOverride ?: $this->bundleQty($nama);
        $qty = $qty ?: max(2, count($komponen));

        $promo = [
            'promo_name' => $nama,
            'promo_type' => 'bundle',
            'discount_percent' => '',
            'komponen' => implode('|', $komponen),
            'catatan' => '',
            'qty' => (string) $qty, // internal (tidak ditulis ke CSV)
            'harga' => (string) ($hargaBundle ?? ''),
        ];

        if (count($komponen) < 2) {
            $promo['catatan'] = 'komponen tidak lengkap — lengkapi manual';

            return $promo;
        }

        $total = 0;

        foreach ($komponen as $judul) {
            $total += $judulMap[$this->norm($judul)]['harga'] ?? 0;
        }

        if ($hargaBundle !== null && $total > 0) {
            $discount = (int) round((1 - $hargaBundle / $total) * 100);

            if ($discount > 50) {
                $promo['catatan'] = 'diskon mencurigakan — periksa komponen manual';
            } else {
                $promo['discount_percent'] = (string) max(1, $discount);
            }
        }

        return $promo;
    }

    /**
     * Resolve komponen bundle ke judul buku yang ada di buku.csv.
     *
     * @param  array<string, array{judul: string, harga: int}>  $judulMap
     * @return list<string>
     */
    private function resolveComponents(string $nama, array $judulMap, int $qtyOverride = 0): array
    {
        // Pola "X & Y" → split literal.
        if (Str::contains($nama, '&')) {
            $found = [];

            foreach (array_filter(array_map('trim', explode('&', $nama))) as $part) {
                $match = $this->findByJudul($part, $judulMap);

                if ($match !== null) {
                    $found[] = $match;
                }
            }

            return $found;
        }

        // Pola "Bundling <kata kunci>".
        $keywords = $this->keywords($nama);
        $qty = $qtyOverride ?: $this->bundleQty($nama);

        if ($keywords === []) {
            return [];
        }

        $scores = [];

        foreach ($judulMap as $normJudul => $data) {
            if (Str::startsWith($normJudul, 'bundling')) {
                continue; // buku bundling tidak bisa jadi komponen bundle
            }

            $score = 0;

            foreach ($keywords as $keyword) {
                if (Str::contains($normJudul, $keyword)) {
                    $score += 2;
                }
            }

            if ($score > 0) {
                $scores[$normJudul] = $score;
            }
        }

        arsort($scores);

        return array_map(
            fn (string $normJudul): string => $judulMap[$normJudul]['judul'],
            array_slice(array_keys($scores), 0, max(2, $qty)),
        );
    }

    /**
     * Cari promo duplikat: key sama atau fuzzy ≥ 0.75 DENGAN harga yang sama.
     *
     * @param  array<string, array<string, string>>  $promos
     */
    private function findPromoDupe(string $key, array $promos, ?int $harga): ?string
    {
        $best = null;
        $bestRatio = 0.0;

        foreach ($promos as $existingKey => $promo) {
            $ratio = $existingKey === $key ? 1.0 : $this->ratio($existingKey, $key);

            if ($ratio >= 0.75 && $ratio > $bestRatio && (string) ($harga ?? '') === $promo['harga']) {
                $best = $existingKey;
                $bestRatio = $ratio;
            }
        }

        return $best;
    }

    /**
     * Cari buku by judul (normalized): exact → contains (dua arah).
     *
     * @param  array<string, array{judul: string, harga: int}>  $judulMap
     */
    private function findByJudul(string $judul, array $judulMap): ?string
    {
        $norm = $this->norm($judul);

        if (isset($judulMap[$norm])) {
            return $judulMap[$norm]['judul'];
        }

        foreach ($judulMap as $candidate => $data) {
            if (strlen($norm) >= 8 && (Str::contains($candidate, $norm) || Str::contains($norm, $candidate))) {
                return $data['judul'];
            }
        }

        return null;
    }

    /**
     * Jumlah buku dari nama: "Bundling 2 Buku ..." / "(2 buku)".
     */
    private function bundleQty(string $nama): int
    {
        if (preg_match('/(\d+)/', $this->norm($nama), $m) === 1) {
            return (int) $m[1];
        }

        return 0;
    }

    /**
     * Kata kunci dari nama bundle (stopword dibuang, prefix me- di-stem).
     *
     * @return list<string>
     */
    private function keywords(string $nama): array
    {
        $stopwords = ['bundling', 'serial', 'buku', 'dan', 'trilogi'];

        $words = preg_split('/[^a-z0-9]+/', strtolower($nama), -1, PREG_SPLIT_NO_EMPTY) ?? [];

        $keywords = [];

        foreach ($words as $word) {
            if (in_array($word, $stopwords, true) || ctype_digit($word)) {
                continue;
            }

            $keywords[] = $word;

            if (Str::startsWith($word, 'men')) {
                $keywords[] = substr($word, 3);
            }
        }

        return array_values(array_unique($keywords));
    }

    /**
     * Nilai % terbanyak (modus) dari kolom % sisi KIRI (diskon guru).
     *
     * @param  array<int, array<string, mixed>>  $kiri
     */
    private function guruDiscountMode(array $kiri): int
    {
        $counts = [];

        foreach ($kiri as $row) {
            $pct = (int) $row['pct'];

            if ($pct > 0) {
                $counts[$pct] = ($counts[$pct] ?? 0) + 1;
            }
        }

        arsort($counts);

        return (int) array_key_first($counts) ?: 30;
    }

    /**
     * Prefix huruf dari kode valid (ALQ000001 → ALQ); '' bila tak ada.
     */
    private function kodePrefix(string $kode): string
    {
        if (preg_match('/^([A-Za-z]+)\d/', $kode, $m) === 1) {
            return $m[1];
        }

        return '';
    }

    /**
     * Cari baris KANAN yang judulnya sama (normalized / fuzzy ≥ 0.88).
     *
     * @param  array<int, array<string, mixed>>  $kanan
     * @return array<string, mixed>|null
     */
    private function findMatch(string $judul, array $kanan): ?array
    {
        $norm = $this->norm($judul);
        $best = null;
        $bestRatio = 0.0;

        foreach ($kanan as $row) {
            $candidate = $this->norm($row['judul']);

            if ($candidate === $norm) {
                return $row;
            }

            $ratio = $this->ratio($candidate, $norm);

            if ($ratio >= 0.88 && $ratio > $bestRatio) {
                $best = $row;
                $bestRatio = $ratio;
            }
        }

        return $best;
    }

    private function ratio(string $a, string $b): float
    {
        return 1 - levenshtein($a, $b) / max(strlen($a), strlen($b), 1);
    }

    private function norm(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower($value));
    }

    private function parseHarga(string $raw): ?int
    {
        $raw = strtoupper(trim($raw));

        if ($raw === '' || str_contains($raw, '#N/A')) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $raw);

        return $digits === '' ? null : (int) $digits;
    }

    /**
     * @return array<int, array{line: int, cells: array<int, string>}>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $rows = [];
        $line = 0;

        while (($cells = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $line++;

            if ($line === 1) {
                continue; // header
            }

            $cells = array_map(fn ($cell): string => trim((string) $cell), $cells);
            $cells = array_pad($cells, 16, '');

            if (count(array_filter($cells, fn ($cell): bool => $cell !== '')) === 0) {
                continue;
            }

            $rows[] = ['line' => $line, 'cells' => $cells];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function writeCsv(string $path, array $headers, array $rows): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $header): mixed => $row[$header] ?? '', $headers));
        }

        fclose($handle);
    }

    private function ensureDir(string $out): void
    {
        if (! is_dir($out)) {
            mkdir($out, 0775, true);
        }
    }
}
