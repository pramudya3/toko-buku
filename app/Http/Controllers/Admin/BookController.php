<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookRequest;
use App\Models\Book;
use App\Models\Category;
use App\Services\BookService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class BookController extends Controller
{
    public function __construct(
        private readonly BookService $bookService,
        private readonly InventoryService $inventoryService,
    ) {
    }

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
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->where('aktif', $request->string('status')->toString() === 'aktif');
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/books/Index', [
            'books' => $books,
            'categories' => Category::orderBy('nama')->get(['id', 'nama']),
            'filters' => $request->only(['search', 'category_id', 'status']),
        ]);
    }

    /**
     * Form buat buku baru.
     */
    public function create(): Response
    {
        return Inertia::render('admin/books/Form', [
            'book' => null,
            'categories' => Category::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    /**
     * Simpan buku baru (BOOK-01, BOOK-03, BOOK-08).
     */
    public function store(BookRequest $request): RedirectResponse
    {
        $data = $this->payload($request);
        $initialStock = (int) ($data['stok'] ?? 0);
        $data['stok'] = 0;
        $adminId = $request->user()->id;

        $book = DB::transaction(function () use ($data, $initialStock, $adminId): Book {
            $book = Book::create($data);
            $this->bookService->ensureSku($book);
            $this->inventoryService->ensureStock($book);

            if ($initialStock > 0) {
                $this->inventoryService->move(
                    book: $book,
                    type: MovementType::In,
                    qty: $initialStock,
                    to: \App\Enums\Warehouse::Malang,
                    userId: $adminId,
                    notes: 'Stok awal buku',
                );
            }

            return $book->fresh();
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
            'categories' => Category::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    /**
     * Update buku (BOOK-08).
     */
    public function update(BookRequest $request, Book $book): RedirectResponse
    {
        $book->update($this->payload($request));

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

        $this->deleteCoverFile($book);

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
     * Hapus file cover dari storage publik (hanya file lokal, bukan URL eksternal).
     */
    private function deleteCoverFile(Book $book): void
    {
        $cover = $book->cover_url;

        if ($cover === null || ! str_starts_with($cover, '/storage/')) {
            return;
        }

        $path = str_replace('/storage/', '', $cover);
        Storage::disk('public')->delete($path);
    }

    /**
     * Payload dari request: cover file disimpan, cover_url dipakai bila tanpa file.
     *
     * @return array<string, mixed>
     */
    private function payload(BookRequest $request): array
    {
        $data = $request->validated();

        unset($data['cover']);

        if ($request->hasFile('cover')) {
            $path = $request->file('cover')->store('covers', 'public');

            if ($path === false) {
                throw new RuntimeException('Cover buku gagal disimpan.');
            }

            $data['cover_url'] = Storage::url($path);
        }

        if ($request->has('aktif')) {
            $data['aktif'] = $request->boolean('aktif');
        }

        $data['is_preorder'] = $request->boolean('is_preorder');

        return $data;
    }
}
