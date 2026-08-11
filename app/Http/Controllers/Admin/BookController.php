<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookRequest;
use App\Models\Book;
use App\Models\BookEdition;
use App\Models\Category;
use App\Services\BookService;
use App\Services\ImageService;
use App\Services\InventoryService;
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
            ->when($request->boolean('low_stock'), function ($query): void {
                $query->where('stok', '<=', config('pricing.low_stock_threshold'));
            })
            ->orderByDesc('created_at')
            ->paginate(10)
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
     * Hapus buku — diblokir bila punya riwayat pesanan (BOOK-04, BR-04).
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

        $book->delete();

        $this->deleteStoredFile((string) $book->cover_url);

        // Hapus file galeri dari storage (baris DB dibiarkan — soft delete,
        // restore tetap menampilkan gambar yang tersisa).
        foreach ($book->images()->get() as $image) {
            $this->deleteStoredFile($image->image_url);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Buku {$book->judul} berhasil dihapus.",
            'undo' => ['url' => route('admin.books.restore', $book)],
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
     * Hapus file dari storage (R2 atau lokal).
     */
    private function deleteStoredFile(string $url): void
    {
        $r2Url = rtrim((string) config('filesystems.disks.r2.url'), '/');

        if ($r2Url !== '' && str_starts_with($url, $r2Url)) {
            $path = ltrim(str_replace($r2Url, '', $url), '/');
            Storage::disk('r2')->delete($path);

            return;
        }

        if (str_starts_with($url, '/storage/')) {
            $path = str_replace('/storage/', '', $url);
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Payload dari request.
     *
     * @return array<string, mixed>
     */
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
                ->store('covers', 'r2');

            if ($path === false) {
                throw new RuntimeException('Cover buku gagal disimpan.');
            }

            $data['cover_url'] = Storage::disk('r2')->url($path);
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
            $path = $this->imageService->normalize($file)->store('covers', 'r2');

            if ($path === false) {
                throw new RuntimeException('Gambar galeri gagal disimpan.');
            }

            $book->images()->create([
                'image_url' => Storage::disk('r2')->url($path),
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
     * @param  array<int, array{cetakan_ke: int, nama?: string|null, harga_beli: int, harga_jual: int, is_active?: bool}>  $editions
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
}
