<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\StockRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StockRequestController extends Controller
{
    /**
     * User login mengajukan pemberitahuan stok untuk buku yang habis.
     * Satu user maksimal satu pengajuan per buku.
     */
    public function store(Request $request, Book $book): RedirectResponse
    {
        StockRequest::firstOrCreate([
            'book_id' => $book->id,
            'user_id' => $request->user()->id,
        ]);

        Inertia::flash(
            'toast',
            'Pengajuan stok berhasil dikirim — kami kabari saat buku tersedia.',
        );

        return back();
    }
}
