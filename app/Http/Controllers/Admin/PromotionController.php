<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Enums\PromotionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionImportRequest;
use App\Http\Requests\Admin\PromotionRequest;
use App\Models\Book;
use App\Models\Promotion;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PromotionController extends Controller
{
    /**
     * Import promo bundle dari file CSV.
     *
     * Kolom (deteksi header): promo_name, promo_type (bundle),
     * discount_percent, komponen (judul buku dipisah |). Kolom bundle_qty
     * legacy diabaikan. Komponen di-resolve by judul (case-insensitive,
     * tanpa spasi/tanda baca).
     */
    public function importCsv(PromotionImportRequest $request): RedirectResponse
    {
        $result = DB::transaction(function () use ($request): array {
            $rows = $this->parseCsvRows($request->file('file')->getRealPath());

            return Promotion::withoutEvents(fn (): array => $this->processPromoRows($rows));
        });

        ActivityLogger::log(
            ActivityAction::PromotionImport,
            "Import CSV promo: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['skipped']} dilewati.",
            null,
            ['summary' => $result],
        );

        $message = "Import CSV selesai: {$result['created']} promo baru, {$result['updated']} diperbarui, {$result['skipped']} dilewati.";

        if ($result['errors'] !== []) {
            $message .= ' '.count($result['errors']).' baris gagal ('.implode('; ', array_slice($result['errors'], 0, 3)).').';
        }

        if ($result['missing'] > 0) {
            $message .= " {$result['missing']} baris dengan komponen tidak ditemukan (lengkapi manual).";
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
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
     * Deteksi posisi kolom dari header (dukung format lama & baru).
     *
     * @param  array<int, string>  $cells
     * @return array{nama: int, tipe: int, diskon: int, komponen: int}|null
     */
    private function detectPromoColumns(array $cells): ?array
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

        $nama = $find(['promo_name', 'nama promo', 'promo name']);

        if ($nama === null) {
            return null;
        }

        return [
            'nama' => $nama,
            'tipe' => $find(['promo_type', 'tipe']) ?? -1,
            'diskon' => $find(['discount_percent', 'discount_percentage', 'diskon']) ?? -1,
            'komponen' => $find(['komponen', 'books', 'buku']) ?? -1,
        ];
    }

    /**
     * @param  array<int, array{line: int, cells: array<int, string>}>  $rows
     * @return array{created: int, updated: int, skipped: int, missing: int, errors: list<string>}
     */
    private function processPromoRows(array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $missing = 0;
        $errors = [];

        if ($rows === []) {
            return compact('created', 'updated', 'skipped', 'missing', 'errors');
        }

        $columns = $this->detectPromoColumns($rows[0]['cells']);
        $isHeader = $columns !== null;

        $bookByJudul = Book::query()
            ->whereNull('deleted_at')
            ->get(['id', 'judul'])
            ->keyBy(fn (Book $book): string => $this->normJudul($book->judul));

        foreach ($rows as $index => $row) {
            $line = $row['line'];
            $cells = $row['cells'];

            if ($isHeader && $index === 0) {
                continue;
            }

            $get = fn (string $key): string => trim((string) ($cells[$columns[$key]] ?? ''));

            $nama = $get('nama');
            $tipe = strtolower($get('tipe')) ?: 'bundle';

            if ($nama === '') {
                $errors[] = "Baris {$line}: promo_name kosong";

                continue;
            }

            if ($tipe !== 'bundle') {
                $errors[] = "Baris {$line} ({$nama}): tipe {$tipe} belum didukung — hanya bundle";

                continue;
            }

            $discount = $this->parseHarga($get('diskon'));
            $komponenRaw = $get('komponen');
            $hasKomponen = $komponenRaw !== '';
            $bookIds = [];
            $unresolved = [];

            if ($hasKomponen) {
                foreach (array_filter(array_map('trim', explode('|', $komponenRaw))) as $judul) {
                    $book = $bookByJudul->get($this->normJudul($judul));

                    if ($book !== null) {
                        $bookIds[] = $book->id;
                    } else {
                        $unresolved[] = $judul;
                    }
                }
            }

            if ($unresolved !== []) {
                $missing++;
            }

            $existing = Promotion::query()
                ->whereRaw('LOWER(promo_name) = ?', [strtolower($nama)])
                ->whereNull('deleted_at')
                ->first();

            if ($existing !== null) {
                $same = $existing->discount_percentage === $discount
                    && (! $hasKomponen || $existing->books()->pluck('books.id')->sort()->values()->all() === collect($bookIds)->sort()->values()->all());

                if ($same) {
                    $skipped++;

                    continue;
                }

                // Update hanya field yang ada di file — tanggal/is_active dan
                // komponen manual (saat kolom komponen kosong) TIDAK ditimpa.
                $existing->update([
                    'promo_name' => $nama,
                    'promo_type' => PromotionType::Bundle,
                    'discount_percentage' => $discount,
                ]);

                if ($hasKomponen) {
                    $existing->books()->sync($bookIds);
                }

                $updated++;

                continue;
            }

            $promotion = Promotion::create([
                'promo_name' => $nama,
                'promo_type' => PromotionType::Bundle,
                'discount_percentage' => $discount,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addYear()->toDateString(),
                'is_active' => true,
            ]);

            if ($hasKomponen) {
                $promotion->books()->sync($bookIds);
            }

            $created++;
        }

        return compact('created', 'updated', 'skipped', 'missing', 'errors');
    }

    private function normJudul(string $judul): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower($judul));
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
     * List promosi + jumlah buku (PROM-01..05).
     */
    public function index(Request $request): Response
    {
        $promotions = Promotion::query()
            ->withCount('books')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->whereLike('promo_name', '%'.$request->string('search')->toString().'%');
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/promotions/Index', [
            'promotions' => $promotions,
            'filters' => $request->only(['search']),
            'typeOptions' => PromotionType::options(),
        ]);
    }

    /**
     * Form buat promosi.
     */
    public function create(): Response
    {
        return Inertia::render('admin/promotions/Form', [
            'promotion' => null,
            'typeOptions' => PromotionType::options(),
            'books' => Book::where('aktif', true)->orderBy('judul')->limit(50)->get(['id', 'judul', 'kode_sku', 'harga', 'cover_url']),
        ]);
    }

    /**
     * Search active books for promotion targeting.
     */
    public function bookOptions(Request $request): JsonResponse
    {
        $search = trim($request->string('search')->toString());

        $books = Book::query()
            ->where('aktif', true)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereLike('judul', "%{$search}%")
                        ->orWhereLike('kode_sku', "%{$search}%");
                });
            })
            ->orderBy('judul')
            ->paginate(20)
            ->withQueryString();

        return response()->json([
            'data' => $books->items(),
            'current_page' => $books->currentPage(),
            'last_page' => $books->lastPage(),
            'total' => $books->total(),
        ]);
    }

    /**
     * Simpan promosi + attach buku (PROM-01, PROM-03).
     */
    public function store(PromotionRequest $request): RedirectResponse
    {
        $promotion = Promotion::create($this->payload($request));

        $promotion->books()->sync($request->boolean('is_global') ? [] : $request->input('book_ids', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Promo {$promotion->promo_name} berhasil dibuat."]);

        return to_route('admin.promotions.index');
    }

    /**
     * Form edit promosi.
     */
    public function edit(Promotion $promotion): Response
    {
        return Inertia::render('admin/promotions/Form', [
            // Muat kolom lengkap agar chip buku terpilih bisa menampilkan judul.
            'promotion' => $promotion->load('books:id,judul,kode_sku,harga'),
            'typeOptions' => PromotionType::options(),
            'books' => Book::where('aktif', true)->orderBy('judul')->limit(50)->get(['id', 'judul', 'kode_sku', 'harga', 'cover_url']),
        ]);
    }

    /**
     * Update promosi (PROM-01).
     */
    public function update(PromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        $promotion->update($this->payload($request));

        $promotion->books()->sync($request->boolean('is_global') ? [] : $request->input('book_ids', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Promo {$promotion->promo_name} berhasil diperbarui."]);

        return to_route('admin.promotions.index');
    }

    /**
     * Hapus promosi.
     */
    public function destroy(Promotion $promotion): RedirectResponse
    {
        $promotion->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Promo {$promotion->promo_name} berhasil dihapus.",
            'undo' => ['url' => route('admin.promotions.restore', $promotion)],
        ]);

        return to_route('admin.promotions.index');
    }

    /**
     * Pulihkan promosi yang dihapus (soft delete).
     */
    public function restore(Promotion $promotion): RedirectResponse
    {
        $promotion->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Promo {$promotion->promo_name} berhasil dipulihkan.",
        ]);

        return to_route('admin.promotions.index');
    }

    /**
     * Toggle aktif/nonaktif cepat (PROM-05).
     */
    public function toggle(Promotion $promotion): RedirectResponse
    {
        $promotion->update(['is_active' => ! $promotion->is_active]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Promo {$promotion->promo_name} di-".($promotion->is_active ? 'aktifkan' : 'nonaktifkan').'.',
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PromotionRequest $request): array
    {
        $data = $request->validated();

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        } else {
            unset($data['is_active']);
        }

        // is_global bukan kolom DB — global ditentukan dari buku kosong (sync []).
        unset($data['book_ids']);
        unset($data['is_global']);

        return $data;
    }
}
