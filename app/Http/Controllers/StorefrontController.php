<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman publik storefront (katalog & detail buku).
 */
class StorefrontController extends Controller
{
    /**
     * Katalog buku — hanya buku aktif, dengan search & filter kategori.
     */
    public function catalog(Request $request): Response
    {
        return Inertia::render('storefront/Catalog', [
            'books' => $this->filteredBooks($request)->paginate(10)->withQueryString(),
            'categories' => Category::orderBy('nama')->get(['id', 'nama']),
            'filters' => $request->only(['search', 'category_id']),
        ]);
    }

    /**
     * Load more — halaman berikutnya dalam bentuk JSON (load-more pagination).
     */
    public function loadMore(Request $request): JsonResponse
    {
        return response()->json(
            $this->filteredBooks($request)->paginate(10),
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Book>
     */
    private function filteredBooks(Request $request)
    {
        return Book::query()
            ->where('aktif', true)
            ->with('category:id,nama')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->whereLike('judul', "%{$search}%")
                        ->orWhereLike('penulis', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->orderBy('judul');
    }

    /**
     * Detail buku publik.
     */
    public function show(Book $book): Response
    {
        abort_unless($book->aktif, 404);

        return Inertia::render('storefront/BookDetail', [
            'book' => $book->load('category:id,nama'),
        ]);
    }

    /**
     * Halaman tentang kami.
     */
    public function about(): Response
    {
        return Inertia::render('storefront/About');
    }
}
