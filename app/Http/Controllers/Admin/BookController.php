<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookImportRequest;
use App\Http\Requests\Admin\BookRequest;
use App\Models\Book;
use App\Models\BookEdition;
use App\Models\BookEditionStock;
use App\Models\Category;
use App\Models\Warehouse;
use App\Services\BookService;
use App\Services\ImageService;
use App\Services\InventoryService;
use App\Support\ActivityLogger;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class BookController extends Controller
{
    public function __construct(
        private readonly BookService $bookService,
        private readonly ImageService $imageService,
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * List buku dengan pencarian & filter (BOOK-05, BOOK-07).
     */
    public function index(Request $request): Response
    {
        $books = Book::query()
            ->with('category:id,nama')
            ->withCount('orderItems')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->whereLike('judul', "%{$search}%")
                        ->orWhereLike('penulis', "%{$search}%")
                        ->orWhereLike('isbn', "%{$search}%")
                        ->orWhereLike('kode_sku', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->string('category_id')->toString()))
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('aktif', $request->string('status')->toString() === 'aktif');
            })
            ->when($request->filled('low_stock'), function ($query) use ($request): void {
                if ($request->string('low_stock')->toString() === 'kosong') {
                    $query->where('stok', 0);

                    return;
                }

                $query->where('stok', '>', 0)
                    ->where('stok', '<=', config('pricing.low_stock_threshold'));
            })
            ->orderBy('judul', 'asc')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return Inertia::render('admin/books/Index', [
            'books' => $books,
            'categories' => Category::orderBy('nama')->get(['id', 'nama']),
            'lowStockThreshold' => config('pricing.low_stock_threshold'),
            'filters' => $request->only(['search', 'category_id', 'status', 'low_stock']),
        ]);
    }

    /**
     * Form buat buku baru.
     */
    public function create(): Response
    {
        return Inertia::render('admin/books/Form', [
            'book' => null,
            'editions' => [],
            'images' => [],
            'categories' => Category::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    /**
     * Simpan buku baru (BOOK-01, BOOK-03, BOOK-08). Stok awal diisi kemudian
     * lewat menu Barang Masuk (pembelian) — bukan di form buku.
     */
    public function store(BookRequest $request): RedirectResponse
    {
        $data = $this->payload($request);
        $editions = $data['editions'] ?? [];
        unset($data['editions']);
        $adminId = $request->user()->id;

        $book = DB::transaction(function () use ($data, $editions, $request): Book {
            $book = Book::create($data);
            $this->bookService->ensureSku($book);
            $this->inventoryService->ensureStock($book);

            // Simpan cetakan-cetakan (stok diatur lewat menu Barang Masuk).
            $this->syncEditions($book, $editions);

            // Galeri gambar
            if ($request->hasFile('images')) {
                $this->saveGalleryImages($book, $request->file('images'));
            }

            // Sinkron books.harga dari cetakan aktif
            $book->refresh();
            $this->syncBookPrice($book);

            return $book;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Buku {$book->judul} berhasil dibuat."]);

        return to_route('admin.books.index');
    }

    /**
     * Form edit buku.
     */
    public function edit(Book $book): Response
    {
        return Inertia::render('admin/books/Form', [
            'book' => $book->load('category:id,nama'),
            'editions' => $book->editions()->orderBy('cetakan_ke')->get()->toArray(),
            'images' => $book->images()->orderBy('urutan')->get(['id', 'image_url', 'urutan'])->toArray(),
            'categories' => Category::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    /**
     * Update buku (BOOK-08).
     */
    public function update(BookRequest $request, Book $book): RedirectResponse
    {
        $data = $this->payload($request, $book);
        $editions = $data['editions'] ?? [];
        unset($data['editions']);

        // Cetakan yang dihapus dari form tidak boleh punya riwayat pemakaian
        // (order/mutasi) atau stok tersisa — kalau tidak, FK buntu → 500,
        // atau stok lenyap tanpa jejak mutasi.
        $blockedEdition = $this->findRemovedEditionInUse($book, $editions);

        if ($blockedEdition !== null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $blockedEdition['message']]);

            return back();
        }

        DB::transaction(function () use ($book, $data, $editions, $request): void {
            $book->update($data);

            // Sinkron cetakan
            $this->syncEditions($book, $editions);

            // Galeri gambar: hapus yang ditandai, simpan yang baru
            if ($request->filled('removed_images')) {
                $this->deleteGalleryImages($book, $request->input('removed_images'));
            }

            if ($request->hasFile('images')) {
                $this->saveGalleryImages($book, $request->file('images'));
            }

            // Sinkron books.harga dari cetakan aktif
            $book->refresh();
            $this->syncBookPrice($book);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Buku {$book->judul} berhasil diperbarui."]);

        return to_route('admin.books.index');
    }

    /**
     * Cek cetakan yang akan dihapus dari form — kembalikan pesan blokir
     * bila cetakan masih dipakai di pesanan/mutasi atau masih punya stok.
     *
     * @param  array<int, array{cetakan_ke: int}>  $editions
     * @return array{message: string}|null
     */
    private function findRemovedEditionInUse(Book $book, array $editions): ?array
    {
        $keptCetakanKe = array_column($editions, 'cetakan_ke');
        $removed = $book->editions()
            ->whereNotIn('cetakan_ke', $keptCetakanKe)
            ->get();

        foreach ($removed as $edition) {
            $label = $edition->nama ?? "Cetakan ke-{$edition->cetakan_ke}";

            if ($edition->orderItems()->exists()) {
                return [
                    'message' => "{$label} sudah dipakai di pesanan — tidak bisa dihapus.",
                ];
            }

            if ($edition->inventoryMovements()->exists()) {
                return [
                    'message' => "{$label} punya riwayat mutasi stok — tidak bisa dihapus.",
                ];
            }

            if ($edition->stocks()->sum('qty') > 0) {
                return [
                    'message' => "{$label} masih menyimpan stok — opname/sesuaikan stoknya dulu.",
                ];
            }
        }

        return null;
    }

    /**
     * Hapus buku — soft delete (dapat dipulihkan). Diblokir bila punya riwayat pesanan.
     */
    public function destroy(Book $book): RedirectResponse
    {
        if ($book->hasOrderHistory()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Buku {$book->judul} memiliki riwayat pesanan dan tidak dapat dihapus.",
            ]);

            return back();
        }

        // Hapus file cover dari storage
        $this->deleteStoredFile((string) $book->cover_url);

        // Hapus file galeri dari storage
        foreach ($book->images()->get() as $image) {
            $this->deleteStoredFile($image->image_url);
        }

        $book->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Buku {$book->judul} berhasil dihapus.",
        ]);

        return to_route('admin.books.index');
    }

    /**
     * Pulihkan buku yang dihapus (soft delete).
     */
    public function restore(Book $book): RedirectResponse
    {
        $book->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Buku {$book->judul} berhasil dipulihkan.",
        ]);

        return to_route('admin.books.index');
    }

    /**
     * Nonaktifkan/aktifkan buku — buku nonaktif tidak tampil di katalog
     * storefront.
     */
    public function toggleActive(Book $book): RedirectResponse
    {
        $book->update(['aktif' => ! $book->aktif]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $book->aktif
                ? "Buku {$book->judul} berhasil diaktifkan."
                : "Buku {$book->judul} berhasil dinonaktifkan.",
        ]);

        return back();
    }

    /**
     * Hapus file dari storage.
     *
     * Mendukung URL lokal (/storage/...) dan URL R2 lama (https://cdn.miniapps.id/...).
     * Hapus dari kedua disk untuk kompatibilitas fake & legacy.
     */
    private function deleteStoredFile(string $url): void
    {
        $r2Url = rtrim((string) config('filesystems.disks.r2.url', ''), '/');
        $path = null;

        if ($r2Url !== '' && str_starts_with($url, $r2Url)) {
            $path = ltrim(str_replace($r2Url, '', $url), '/');
        } elseif (str_contains($url, '/storage/')) {
            $path = ltrim(substr($url, (int) strpos($url, '/storage/') + strlen('/storage/')), '/');
        } elseif (preg_match('#(logos|article-images|covers)/.+#', $url, $m)) {
            $path = $m[0];
        } else {
            return;
        }

        Storage::disk('public')->delete($path);

        try {
            Storage::disk('r2')->delete($path);
        } catch (\Throwable) {
            // r2 disk mungkin belum dikonfigurasi
        }
    }

    /**
     * Payload dari request.
     *
     * @return array<string, mixed>
     */
    /**
     * Import buku dari file CSV (IMPORT-BOOK-01).
     *
     * Mendukung dua layout: kolom dengan header (Kategori, Kode Brg,
     * Nama Barang, Penulis, Hrg Jual) maupun file "INVOICE - PRICELIST"
     * yang menaruh kolom tersebut di posisi 9–15. Harga format Indonesia
     * (30.000), #N/A dianggap kosong. Kode SKU anomali dilewati dan
     * di-generate otomatis dari abreviasi kategori (PRN000001 dst.).
     */
    public function importCsv(BookImportRequest $request): RedirectResponse
    {
        $result = DB::transaction(function () use ($request): array {
            $rows = $this->parseCsvRows($request->file('file')->getRealPath());

            return Book::withoutEvents(fn (): array => $this->processBookRows($rows));
        });

        ActivityLogger::log(
            ActivityAction::BookImport,
            "Import CSV buku: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['skipped']} dilewati.",
            null,
            ['summary' => $result],
        );

        $message = "Import CSV selesai: {$result['created']} buku baru, {$result['updated']} diperbarui, {$result['skipped']} dilewati.";

        if ($result['errors'] !== []) {
            $message .= ' '.count($result['errors']).' baris gagal ('.implode('; ', array_slice($result['errors'], 0, 3)).').';
        }

        if ($result['bundling'] > 0) {
            $message .= " {$result['bundling']} baris bundling dilewati (gunakan import Promo).";
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * Parse file CSV: strip BOM, buang baris kosong. Baris header TIDAK
     * dibuang — dipakai untuk deteksi posisi kolom.
     *
     * @return array<int, array{line: int, cells: array<int, string>}>
     */
    private function parseCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Tidak dapat membaca file CSV.');
        }

        $rows = [];
        $lineNumber = 0;

        while (($cells = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $lineNumber++;
            $cells = array_map(fn ($cell): string => trim((string) $cell), $cells);

            if ($lineNumber === 1) {
                $cells[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', $cells[0] ?? '');
            }

            if (count(array_filter($cells, fn ($cell): bool => $cell !== '')) === 0) {
                continue;
            }

            $rows[] = ['line' => $lineNumber, 'cells' => $cells];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Deteksi posisi kolom dari baris header; fallback ke layout 16 kolom
     * (INVOICE) atau 5 kolom polos bila header tidak dikenali.
     *
     * Prioritas layout KANAN (INVOICE): Kategori, Kode Brg, Nama Barang,
     * Penulis, Hrg Jual — bila ada, sisi kiri (PRICELIST: Judul Buku/Harga
     * Normal) diabaikan supaya kolom tidak tercampur.
     *
     * @param  array<int, string>  $cells
     * @return array{kategori: int, kode: int, judul: int, penulis: int, harga: int, harga_beli: int, qty: int}
     */
    private function detectBookColumns(array $cells): array
    {
        $keys = array_map(fn (string $cell): string => strtolower(trim($cell)), $cells);

        $find = function (array $keywords) use ($keys): ?int {
            foreach ($keys as $index => $key) {
                if (in_array($key, $keywords, true)) {
                    return $index;
                }
            }

            return null;
        };

        $hasRightLayout = $find(['nama barang', 'nama_barang']) !== null
            || $find(['kode brg', 'kode barang', 'kode_brg']) !== null
            || $find(['hrg jual', 'harga jual', 'harga_jual']) !== null;

        if ($hasRightLayout) {
            return [
                'kategori' => $find(['kategori']) ?? -1,
                'kode' => $find(['kode brg', 'kode barang', 'kode', 'kode_brg']) ?? -1,
                'judul' => $find(['nama barang', 'nama_barang']) ?? $find(['judul', 'judul buku']) ?? -1,
                'penulis' => $find(['penulis']) ?? -1,
                'harga' => $find(['hrg jual', 'harga jual', 'harga_jual']) ?? -1,
                'harga_beli' => $find(['harga beli', 'hrg beli', 'harga_beli']) ?? -1,
                'qty' => $find(['qty', 'stok', 'stock', 'jumlah', 'jumlah stok']) ?? -1,
            ];
        }

        $judul = $find(['judul buku', 'judul']);
        $harga = $find(['harga normal', 'hrg normal']);

        if ($judul !== null && $harga !== null) {
            return ['kategori' => -1, 'kode' => -1, 'judul' => $judul, 'penulis' => -1, 'harga' => $harga, 'harga_beli' => -1, 'qty' => -1];
        }

        // Header tidak dikenali → asumsikan posisi kolom berdasarkan lebar baris.
        return count($cells) >= 10
            ? ['kategori' => 9, 'kode' => 10, 'judul' => 11, 'penulis' => 12, 'harga' => 13, 'harga_beli' => -1, 'qty' => -1]
            : ['kategori' => 0, 'kode' => 1, 'judul' => 2, 'penulis' => 3, 'harga' => 4, 'harga_beli' => -1, 'qty' => 6];
    }

    /**
     * '30.000' → 30000; '#N/A' / kosong → null.
     */
    private function parseHarga(string $raw): ?int
    {
        $raw = strtoupper(trim($raw));

        if ($raw === '' || str_contains($raw, '#N/A')) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $raw);

        return $digits === null || $digits === '' ? null : (int) $digits;
    }

    /**
     * '1,162' → 1162; kosong → null.
     */
    private function parseQty(string $raw): ?int
    {
        $raw = trim($raw);

        if ($raw === '' || str_contains(strtoupper($raw), '#N/A')) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $raw);

        return $digits === null || $digits === '' ? null : (int) $digits;
    }

    /**
     * @param  array<int, array{line: int, cells: array<int, string>}>  $rows
     * @return array{created: int, updated: int, skipped: int, bundling: int, errors: list<string>}
     */
    private function processBookRows(array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $bundling = 0;
        $errors = [];

        $first = $rows[0]['cells'];
        $columns = $this->detectBookColumns($first);
        $skipFirst = $this->isBookHeaderRow($first);

        // Pre-pass: kumpulkan abreviasi kategori dari SEMUA kode valid di file
        // (mis. PRN000001 → PRN), supaya baris pertama dengan kode rusak pun
        // tetap mendapat SKU berbasis kategori.
        $categoryKodes = [];

        // SKU yang sudah terpakai: dari DB + semua kode valid di file — supaya
        // auto-generate tidak menabrak kode CSV yang datang belakangan (mis.
        // baris kode '1' → PRN000001, padahal PRN000001 sudah dipakai baris lain).
        $usedSkus = Book::query()
            ->whereNotNull('kode_sku')
            ->pluck('kode_sku')
            ->flip()
            ->all();

        foreach ($rows as $index => $row) {
            if ($skipFirst && $index === 0) {
                continue;
            }

            $kode = trim((string) ($row['cells'][$columns['kode']] ?? ''));
            $kategori = strtolower(trim((string) ($row['cells'][$columns['kategori']] ?? '')));

            if ($kategori !== ''
                && preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9-]{2,20}$/', $kode) === 1) {
                $usedSkus[$kode] = true;

                if (preg_match('/^([A-Za-z]+)\d/', $kode, $matches) === 1) {
                    $categoryKodes[$kategori] ??= $matches[1];
                }
            }
        }

        foreach ($rows as $index => $row) {
            if ($skipFirst && $index === 0) {
                continue;
            }

            $line = $row['line'];
            $cells = $row['cells'];

            $cell = fn (string $key): string => trim((string) ($cells[$columns[$key]] ?? ''));

            $kategori = $cell('kategori');
            $kode = $cell('kode');
            $judul = $cell('judul');
            $penulis = $cell('penulis');
            $hargaRaw = $cell('harga');
            $hargaBeliRaw = $cell('harga_beli');
            $qtyRaw = $columns['qty'] !== -1 ? $cell('qty') : '';
            $qty = $this->parseQty($qtyRaw);

            // Baris bundling bukan buku — di-handle oleh import Promo.
            if (preg_replace('/[^a-z0-9]/', '', strtolower($kategori)) === 'bundling') {
                $bundling++;

                continue;
            }

            if ($judul === '') {
                $errors[] = "Baris {$line}: nama barang kosong";

                continue;
            }

            $harga = $this->parseHarga($hargaRaw);

            if ($harga === null) {
                $errors[] = "Baris {$line} ({$judul}): harga jual tidak valid";

                continue;
            }

            // Kode SKU valid: huruf + angka (tolak '1', 'BAI', 'Bundling Sifat').
            $kodeValid = preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z0-9-]{2,20}$/', $kode) === 1 ? $kode : null;
            $penulis = $penulis !== '' ? $penulis : null;
            $hargaBeli = $this->parseHarga($hargaBeliRaw);
            $category = $this->resolveCategory($kategori, $categoryKodes[strtolower($kategori)] ?? null);

            $existing = $this->findExistingBook($kodeValid, $judul);

            if ($existing !== null) {
                $edition = $existing->editions()->where('cetakan_ke', 1)->first()
                    ?? $existing->editions()->first();

                $same = $existing->judul === $judul
                    && $existing->penulis === $penulis
                    && $existing->category_id === $category?->id
                    && $existing->kode_sku === ($kodeValid ?? $existing->kode_sku)
                    && (int) ($edition?->harga_jual ?? -1) === $harga
                    && ($hargaBeli === null || (int) ($edition?->harga_beli ?? -1) === $hargaBeli)
                    && ($qty === null || (int) data_get($edition?->stocks()->whereHas('warehouse', fn ($q) => $q->where('kode', 'malang'))->first(), 'qty', 0) === $qty);

                if ($same) {
                    // Sync qty tetap jika ada (kasus stok 0 dihapus) walau data buku sama
                    $this->syncEditionQty($existing, $qty);
                    $skipped++;

                    continue;
                }

                $existing->forceFill([
                    'judul' => $judul,
                    'penulis' => $penulis,
                    'category_id' => $category?->id,
                    'kode_sku' => $kodeValid ?? $existing->kode_sku,
                ])->save();

                $this->upsertEdition($existing, $harga, $hargaBeli);
                $this->syncBookHarga($existing, $harga);
                $this->syncEditionQty($existing, $qty);

                $updated++;

                continue;
            }

            $book = Book::create([
                'judul' => $judul,
                'penulis' => $penulis,
                'category_id' => $category?->id,
                'kode_sku' => $kodeValid ?? $this->allocateCategorySku($category, $usedSkus),
                'aktif' => true,
                'stok' => 0,
            ]);

            $this->bookService->ensureSku($book);
            $this->inventoryService->ensureStock($book);
            $this->upsertEdition($book, $harga, $hargaBeli);
            $this->syncBookHarga($book, $harga);
            $this->syncEditionQty($book, $qty);

            $created++;
        }

        return compact('created', 'updated', 'skipped', 'bundling', 'errors');
    }

    /**
     * Deteksi baris header buku (salah satu sel berisi nama kolom).
     *
     * @param  array<int, string>  $cells
     */
    private function isBookHeaderRow(array $cells): bool
    {
        $keywords = ['kategori', 'kode brg', 'kode barang', 'kode', 'nama barang',
            'judul buku', 'judul', 'penulis', 'hrg jual', 'harga jual', 'harga_jual',
            'harga normal', 'hrg normal', 'harga_beli', 'qty', 'stok', 'stock', 'no', ];

        foreach ($cells as $cell) {
            if (in_array(strtolower(trim($cell)), $keywords, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Alokasi SKU `{kode}{6 digit}` berikutnya yang belum terpakai (DB + file).
     *
     * @param  array<string, true>  $usedSkus
     */
    private function allocateCategorySku(?Category $category, array &$usedSkus): ?string
    {
        $kode = $category?->kode;

        if ($kode === null) {
            return null;
        }

        for ($i = 1; $i <= 999_999; $i++) {
            $sku = $kode.str_pad((string) $i, 6, '0', STR_PAD_LEFT);

            if (! isset($usedSkus[$sku])) {
                $usedSkus[$sku] = true;

                return $sku;
            }
        }

        throw new RuntimeException("SKU untuk kategori {$kode} sudah habis.");
    }

    /**
     * Ambil kategori by nama (case-insensitive); buat bila belum ada dan isi
     * kode abreviasi dari pre-pass kode valid (ALQ000001 → ALQ).
     */
    private function resolveCategory(string $nama, ?string $kodeAbreviasi): ?Category
    {
        $nama = trim($nama);

        if ($nama === '') {
            return null;
        }

        $category = Category::query()
            ->whereRaw('LOWER(nama) = ?', [strtolower($nama)])
            ->whereNull('deleted_at')
            ->first();

        if ($category === null) {
            $category = Category::create(['nama' => $nama]);
        }

        if ($category->kode === null && $kodeAbreviasi !== null) {
            $category->update(['kode' => $kodeAbreviasi]);
        }

        return $category;
    }

    /**
     * Cari buku existing: by kode_sku bila valid, fallback by judul.
     */
    private function findExistingBook(?string $kodeValid, string $judul): ?Book
    {
        if ($kodeValid !== null) {
            $book = Book::query()
                ->where('kode_sku', $kodeValid)
                ->whereNull('deleted_at')
                ->first();

            if ($book !== null) {
                return $book;
            }
        }

        return Book::query()
            ->whereRaw('LOWER(judul) = ?', [strtolower($judul)])
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Cetakan ke-1 sebagai harga jual utama; harga_beli diisi bila tersedia
     * (kolom default 0 untuk cetakan baru, harga_beli existing tidak ditimpa
     * bila file tidak memuat kolom harga_beli).
     */
    private function upsertEdition(Book $book, int $harga, ?int $hargaBeli = null): void
    {
        $values = ['harga_jual' => $harga, 'is_active' => true];

        if ($hargaBeli !== null) {
            $values['harga_beli'] = $hargaBeli;
        }

        BookEdition::updateOrCreate(
            ['book_id' => $book->id, 'cetakan_ke' => 1],
            $values,
        );
    }

    /**
     * Sinkron books.harga dari harga jual (pola sama dengan syncBookPrice).
     */
    private function syncBookHarga(Book $book, int $harga): void
    {
        if ((int) $book->getRawOriginal('harga') !== $harga) {
            $book->updateQuietly(['harga' => $harga]);
        }
    }

    private function payload(BookRequest $request, ?Book $book = null): array
    {
        $data = $request->validated();

        unset($data['cover'], $data['images'], $data['removed_images'], $data['remove_cover']);

        if ($request->hasFile('cover')) {
            if ($book !== null) {
                $this->deleteStoredFile((string) $book->cover_url);
            }

            $path = $this->imageService
                ->normalize($request->file('cover'))
                ->store('covers', 'public');

            if ($path === false) {
                throw new RuntimeException('Cover buku gagal disimpan.');
            }

            $data['cover_url'] = Storage::disk('public')->url($path);
        }

        // Hapus cover tersimpan (tanpa upload file baru).
        if ($request->boolean('remove_cover') && ! $request->hasFile('cover')) {
            if ($book !== null) {
                $this->deleteStoredFile((string) $book->cover_url);
            }

            $data['cover_url'] = null;
        }

        if ($request->has('aktif')) {
            $data['aktif'] = $request->boolean('aktif');
        }

        if ($request->has('is_preorder')) {
            $data['is_preorder'] = $request->boolean('is_preorder');
        }

        return $data;
    }

    /**
     * Simpan gambar galeri baru.
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function saveGalleryImages(Book $book, array $files): void
    {
        $urutan = (int) $book->images()->max('urutan');

        foreach ($files as $file) {
            $path = $this->imageService->normalize($file)->store('covers', 'public');

            if ($path === false) {
                throw new RuntimeException('Gambar galeri gagal disimpan.');
            }

            $book->images()->create([
                'image_url' => Storage::disk('public')->url($path),
                'urutan' => ++$urutan,
            ]);
        }
    }

    /**
     * Hapus gambar galeri yang ditandai (row + file di storage).
     *
     * @param  array<int, string>  $ids
     */
    private function deleteGalleryImages(Book $book, array $ids): void
    {
        $images = $book->images()->whereIn('id', $ids)->get();

        foreach ($images as $image) {
            $this->deleteStoredFile($image->image_url);
            $image->delete();
        }
    }

    /**
     * Sinkron daftar cetakan: hapus yang hilang, update yang ada, buat yang baru.
     *
     * @param  array<int, array{cetakan_ke: int, nama?: string|null, harga_beli: int, harga_jual: int, harga_guru_type?: string|null, harga_guru_value?: int|null, is_active?: bool}>  $editions
     * @return Collection<int, BookEdition>
     */
    private function syncEditions(Book $book, array $editions): Collection
    {
        // Hapus cetakan yang tidak ada di input
        $keptCetakanKe = array_column($editions, 'cetakan_ke');
        $book->editions()->whereNotIn('cetakan_ke', $keptCetakanKe)->delete();

        // Hanya satu yang bisa aktif
        $activeIndex = collect($editions)->search(fn ($e) => (bool) ($e['is_active'] ?? false));

        $created = collect();

        foreach ($editions as $index => $edition) {
            $model = BookEdition::updateOrCreate(
                [
                    'book_id' => $book->id,
                    'cetakan_ke' => $edition['cetakan_ke'],
                ],
                [
                    'nama' => $edition['nama'] ?? null,
                    'harga_beli' => $edition['harga_beli'],
                    'harga_jual' => $edition['harga_jual'],
                    'harga_guru_type' => $edition['harga_guru_type'] ?? null,
                    'harga_guru_value' => $edition['harga_guru_value'] ?? null,
                    'is_active' => $index === $activeIndex || count($editions) === 1 || ($activeIndex === false && $index === 0),
                ],
            );

            // Cetakan baru (bukan update) — dipakai untuk stok awal.
            if ($model->wasRecentlyCreated) {
                $created->push($model);
            }
        }

        return $created;
    }

    /**
     * Sinkron books.harga dari harga_jual cetakan aktif.
     *
     * Bandingkan nilai mentah (bukan int cast) — null !== 0, jadi buku
     * dengan harga null tetap tersinkron walau harga_jual-nya 0.
     */
    private function syncBookPrice(Book $book): void
    {
        $active = $book->editions()->where('is_active', true)->first()
            ?? $book->editions()->orderBy('cetakan_ke')->first();

        if ($active !== null && $book->getRawOriginal('harga') !== $active->harga_jual) {
            $book->updateQuietly(['harga' => $active->harga_jual]);
        }
    }

    /**
     * Sinkron qty cetakan ke-1 dari kolom qty/stok CSV (jika ada).
     * Qty null = kolom tidak ada/kosong → tidak ubah stok.
     * Qty 0 → stok di-hapus/sync 0.
     */
    private function syncEditionQty(Book $book, ?int $qty): void
    {
        if ($qty === null) {
            return;
        }

        $edition = $book->editions()->where('cetakan_ke', 1)->first()
            ?? $book->editions()->orderBy('cetakan_ke')->first();

        if ($edition === null) {
            return;
        }

        $warehouse = Warehouse::query()->where('kode', 'malang')->first()
            ?? Warehouse::default();

        if ($qty > 0) {
            BookEditionStock::updateOrCreate(
                ['book_edition_id' => $edition->id, 'warehouse_id' => $warehouse->id],
                ['qty' => $qty],
            );
        } else {
            $edition->stocks()->where('warehouse_id', $warehouse->id)->delete();
        }

        $this->inventoryService->syncBookStock($book);
    }
}
