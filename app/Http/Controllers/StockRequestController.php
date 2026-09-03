<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockRequestRequest;
use App\Models\Book;
use App\Models\StockRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StockRequestController extends Controller
{
    /**
     * User login mengajukan pemberitahuan stok untuk buku yang habis.
     * Satu user maksimal satu pengajuan per buku.
     *
     * Nomor WhatsApp wajib diisi (dialog konfirmasi) dan disimpan ke profil
     * user — nomor lama diganti bila berubah.
     */
    public function store(StoreStockRequestRequest $request, Book $book): RedirectResponse
    {
        $validated = $request->validated();

        $user = $request->user();

        if ($user->whatsapp_number !== $validated['whatsapp_number']) {
            $user->update(['whatsapp_number' => $validated['whatsapp_number']]);
        }

        StockRequest::firstOrCreate([
            'book_id' => $book->id,
            'user_id' => $user->id,
        ]);

        Inertia::flash(
            'toast',
            'Pengajuan stok berhasil dikirim — kami kabari saat buku tersedia.',
        );

        return back();
    }
}
