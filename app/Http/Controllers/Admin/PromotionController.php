<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PromotionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionRequest;
use App\Models\Book;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
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
