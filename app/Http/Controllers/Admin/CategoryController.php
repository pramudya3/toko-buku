<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryImportRequest;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CategoryController extends Controller
{
    /**
     * List kategori + jumlah buku per kategori (CAT-02).
     */
    public function index(Request $request): Response
    {
        $categories = Category::query()
            ->withCount('books')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->whereLike('nama', '%'.$request->string('search')->toString().'%');
            })
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/categories/Index', [
            'categories' => $categories,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Import kategori dari file CSV (kolom: kode, nama).
     */
    public function importCsv(CategoryImportRequest $request): RedirectResponse
    {
        $result = DB::transaction(function () use ($request): array {
            $rows = $this->parseCsvRows($request->file('file')->getRealPath());

            return Category::withoutEvents(fn (): array => $this->processCategoryRows($rows));
        });

        ActivityLogger::log(
            ActivityAction::CategoryImport,
            "Import CSV kategori: {$result['created']} dibuat, {$result['updated']} diperbarui, {$result['skipped']} dilewati.",
            null,
            ['summary' => $result],
        );

        $message = "Import CSV selesai: {$result['created']} kategori baru, {$result['updated']} diperbarui, {$result['skipped']} dilewati.";

        if ($result['errors'] !== []) {
            $message .= ' '.count($result['errors']).' baris gagal ('.implode('; ', array_slice($result['errors'], 0, 3)).').';
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
        $isFirstRow = true;

        while (($cells = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $lineNumber++;
            $cells = array_map(fn ($cell): string => trim((string) $cell), $cells);

            if ($isFirstRow) {
                $isFirstRow = false;
                $cells[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', $cells[0] ?? '');

                if (count(array_filter($cells, fn ($cell): bool => $cell !== '')) === 0) {
                    continue;
                }

                // Baris header: kode,nama → kolom 0,1.
                if (strtolower($cells[0]) === 'kode' || strtolower($cells[1] ?? '') === 'nama') {
                    continue;
                }
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
     * @param  array<int, array{line: int, cells: array<int, string>}>  $rows
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    private function processCategoryRows(array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            $line = $row['line'];
            $cells = $row['cells'];
            $kode = trim($cells[0] ?? '');
            $nama = trim($cells[1] ?? '');

            if ($nama === '') {
                $errors[] = "Baris {$line}: nama kosong";

                continue;
            }

            $category = $kode !== ''
                ? Category::query()->where('kode', $kode)->whereNull('deleted_at')->first()
                : null;

            $category ??= Category::query()
                ->whereRaw('LOWER(nama) = ?', [strtolower($nama)])
                ->whereNull('deleted_at')
                ->first();

            if ($category !== null) {
                if ($category->kode === $kode && $category->nama === $nama) {
                    $skipped++;

                    continue;
                }

                $category->update([
                    'nama' => $nama,
                    'kode' => $kode !== '' ? $kode : $category->kode,
                ]);

                $updated++;

                continue;
            }

            Category::create(['nama' => $nama, 'kode' => $kode !== '' ? $kode : null]);

            $created++;
        }

        return compact('created', 'updated', 'skipped', 'errors');
    }

    /**
     * Simpan kategori baru (CAT-01).
     */
    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kategori {$category->nama} berhasil dibuat."]);

        return to_route('admin.categories.index');
    }

    /**
     * Update kategori (CAT-01).
     */
    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Kategori {$category->nama} berhasil diperbarui."]);

        return to_route('admin.categories.index');
    }

    /**
     * Hapus kategori — diblokir bila masih dipakai buku (CAT-03).
     */
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->books()->withTrashed()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Kategori {$category->nama} masih dipakai buku dan tidak dapat dihapus.",
            ]);

            return back();
        }

        $category->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Kategori {$category->nama} berhasil dihapus.",
            'undo' => ['url' => route('admin.categories.restore', $category)],
        ]);

        return to_route('admin.categories.index');
    }

    /**
     * Pulihkan kategori yang dihapus (soft delete).
     */
    public function restore(Category $category): RedirectResponse
    {
        $category->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Kategori {$category->nama} berhasil dipulihkan.",
        ]);

        return to_route('admin.categories.index');
    }
}
