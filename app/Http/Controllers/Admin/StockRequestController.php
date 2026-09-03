<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\StockRequest;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class StockRequestController extends Controller
{
    /**
     * Daftar buku yang diajukan — per buku: stok saat ini, jumlah pengaju,
     * dan pengajuan terakhir. Buku pre-order ditangani di halaman Pre-Order.
     */
    public function index(Request $request): Response
    {
        $requests = StockRequest::query()
            ->whereHas('book', fn ($bookQuery) => $bookQuery->where('is_preorder', false))
            ->with('book:id,judul,cover_url,stok,is_preorder')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->whereHas('book', fn ($bookQuery) => $bookQuery->whereLike('judul', "%{$request->string('search')->toString()}%"));
            })
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('book_id')
            ->map(function (Collection $items, string $bookId): array {
                $book = $items->first()?->book;

                return [
                    'book' => [
                        'id' => $bookId,
                        'judul' => $book->judul,
                        'cover_url' => $book->cover_url,
                        'stok' => $book->stok,
                    ],
                    'total_requests' => $items->count(),
                    'last_requested_at' => $items->max('created_at')?->toDateTimeString(),
                ];
            })
            ->sortByDesc('last_requested_at')
            ->values();

        $page = max(1, $request->integer('page', 1));
        $perPage = Pagination::perPage($request);

        $paginator = new LengthAwarePaginator(
            $requests->forPage($page, $perPage),
            $requests->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('admin/stock-requests/Index', [
            'requests' => $paginator,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Detail pengajuan stok sebuah buku — daftar user yang mengajukan.
     */
    public function show(Request $request, Book $book): Response
    {
        $requests = StockRequest::query()
            ->where('book_id', $book->id)
            ->with('user:id,name,email,whatsapp_number')
            ->orderByDesc('created_at')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return Inertia::render('admin/stock-requests/Show', [
            'book' => [
                'id' => $book->id,
                'judul' => $book->judul,
                'cover_url' => $book->cover_url,
                'stok' => $book->stok,
            ],
            'requests' => $requests->through(fn (StockRequest $request) => [
                'id' => $request->id,
                'user' => [
                    'id' => $request->user->id,
                    'name' => $request->user->name,
                    'email' => $request->user->email,
                    'whatsapp_number' => $request->user->whatsapp_number,
                ],
                'created_at' => $request->created_at,
            ]),
        ]);
    }
}
