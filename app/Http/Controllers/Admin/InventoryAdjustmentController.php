<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InventoryAdjustmentRequest;
use App\Models\Book;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Koreksi stok hasil opname fisik (stock adjustment) + riwayatnya.
 */
class InventoryAdjustmentController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Form koreksi stok + riwayat adjustment terakhir.
     */
    public function index(): Response
    {
        $adjustments = InventoryMovement::query()
            ->where('type', MovementType::Adjustment)
            ->with([
                'book:id,judul,kode_sku',
                'edition:id,book_id,cetakan_ke',
                'fromWarehouse:id,kode,nama',
                'toWarehouse:id,kode,nama',
                'user:id,name',
            ])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('admin/inventory-adjustments/Index', [
            'adjustments' => $adjustments,
            'warehouses' => Warehouse::query()
                ->orderBy('is_defect')
                ->orderBy('nama')
                ->get(['id', 'kode', 'nama', 'is_defect', 'is_active']),
        ]);
    }

    /**
     * Pilihan buku untuk picker adjustment — paginated agar semua buku
     * bisa di-browse (bukan hanya hasil pencarian). Buku non-aktif pun
     * bisa di-adjust karena masih mungkin punya stok fisik.
     */
    public function bookOptions(Request $request): JsonResponse
    {
        $books = Book::query()
            ->with('editions:id,book_id,cetakan_ke,harga_jual,is_active')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

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
     * Simpan koreksi stok opname.
     */
    public function store(InventoryAdjustmentRequest $request): RedirectResponse
    {
        $book = Book::findOrFail($request->string('book_id')->toString());
        $edition = $request->filled('book_edition_id')
            ? $book->editions()->findOrFail($request->string('book_edition_id')->toString())
            : null;
        $warehouse = Warehouse::findOrFail($request->string('warehouse_id')->toString());

        try {
            $this->inventory->adjust(
                book: $book,
                warehouse: $warehouse,
                qty: $request->integer('qty'),
                userId: $request->user()->id,
                notes: $request->string('notes')->toString(),
                edition: $edition,
            );

            $label = $book->judul;

            if ($edition !== null) {
                $label .= " (Cetakan ke-{$edition->cetakan_ke})";
            }

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => "Stok adjustment untuk {$label} tercatat.",
            ]);
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return back();
    }
}
