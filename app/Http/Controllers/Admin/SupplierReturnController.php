<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupplierReturnRequest;
use App\Models\Book;
use App\Models\Supplier;
use App\Models\SupplierPurchase;
use App\Models\SupplierReturn;
use App\Services\SupplierService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pencatatan retur barang ke supplier dengan alasan — menu terpisah dari
 * CRUD supplier. Sumber stok per item: defect (cacat) atau normal (alasan
 * lain). Retur mengurangi hutang.
 */
class SupplierReturnController extends Controller
{
    public function __construct(private readonly SupplierService $supplierService) {}

    /**
     * List retur + filter supplier & rentang tanggal.
     */
    public function index(Request $request): Response
    {
        $returns = SupplierReturn::query()
            ->with('supplier:id,nama', 'items.book:id,judul,kode_sku')
            ->withCount('items')
            ->when($request->filled('supplier_id'), fn ($query) => $query->where('supplier_id', $request->string('supplier_id')->toString()))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('return_date', '>=', $request->string('from')->toString()))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('return_date', '<=', $request->string('to')->toString()))
            ->orderByDesc('return_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/supplier-returns/Index', [
            'returns' => $returns,
            'suppliers' => Supplier::orderBy('nama')->get(['id', 'nama']),
            'filters' => $request->only(['supplier_id', 'from', 'to']),
        ]);
    }

    /**
     * Form catat retur ke supplier.
     */
    public function create(): Response
    {
        return Inertia::render('admin/supplier-returns/Create', [
            'suppliers' => Supplier::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    /**
     * Simpan retur: stok (defect/normal) berkurang + hutang berkurang.
     */
    public function store(SupplierReturnRequest $request): RedirectResponse
    {
        $validated = $request->safe();
        $supplier = Supplier::findOrFail($validated->string('supplier_id')->toString());

        try {
            $return = $this->supplierService->recordReturn(
                supplier: $supplier,
                returnDate: $validated->string('return_date')->toString(),
                items: $validated->input('items'),
                purchaseId: $validated->string('purchase_id')->toString() ?: null,
                notes: $validated->string('notes')->toString() !== '' ? $validated->string('notes')->toString() : null,
                userId: $request->user()?->id,
            );
        } catch (\Throwable $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $e instanceof \RuntimeException ? $e->getMessage() : 'Gagal menyimpan retur.',
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Retur senilai Rp '.number_format($return->total).' tercatat.',
        ]);

        ActivityLogger::log(
            ActivityAction::SupplierReturnCreate,
            'Retur supplier '.($return->supplier->nama ?? '-').' (Rp '.number_format($return->total).')',
            $return,
        );

        return to_route('admin.supplier-returns.index');
    }

    /**
     * Pilihan buku berstok (defect maupun normal) untuk form retur.
     */
    public function bookOptions(Request $request): JsonResponse
    {
        $search = trim($request->string('search')->toString());

        return response()->json(
            Book::query()
                ->where('aktif', true)
                ->whereHas('inventoryStocks', fn ($query) => $query->where('qty', '>', 0))
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->whereLike('judul', "%{$search}%")
                            ->orWhereLike('kode_sku', "%{$search}%");
                    });
                })
                ->with('inventoryStocks.warehouse')
                ->orderBy('judul')
                ->limit(50)
                ->get()
                ->map(function (Book $book): array {
                    $stockDefect = $book->inventoryStocks
                        ->filter(fn ($stock) => $stock->warehouse->is_defect)
                        ->sum('qty');
                    $stockNormal = $book->inventoryStocks
                        ->reject(fn ($stock) => $stock->warehouse->is_defect)
                        ->sum('qty');
                    $book->unsetRelation('inventoryStocks');

                    return [
                        'id' => $book->id,
                        'judul' => $book->judul,
                        'kode_sku' => $book->kode_sku,
                        'stock_defect' => $stockDefect,
                        'stock_normal' => $stockNormal,
                    ];
                }),
        );
    }

    /**
     * Pilihan faktur pembelian milik supplier (untuk menautkan retur).
     */
    public function purchaseOptions(Request $request): JsonResponse
    {
        $supplierId = $request->string('supplier_id')->toString();

        return response()->json(
            SupplierPurchase::query()
                ->where('supplier_id', $supplierId)
                ->orderByDesc('purchase_date')
                ->orderByDesc('id')
                ->get(['id', 'ref_code', 'purchase_date', 'warehouse_kode'])
                ->map(fn (SupplierPurchase $purchase): array => [
                    'id' => $purchase->id,
                    'ref_code' => $purchase->ref_code,
                    'purchase_date' => $purchase->purchase_date,
                    'warehouse_nama' => $purchase->warehouse?->nama ?? '',
                ]),
        );
    }

    /**
     * Baris item faktur (untuk prefill harga & batas qty retur).
     */
    public function purchaseDetail(SupplierPurchase $supplierPurchase): JsonResponse
    {
        return response()->json(
            $supplierPurchase->items()
                ->with('book:id,judul')
                ->get(['id', 'book_id', 'qty', 'price'])
                ->map(fn ($item): array => [
                    'book_id' => $item->book_id,
                    'judul' => $item->book->judul,
                    'qty' => $item->qty,
                    'price' => $item->price,
                ]),
        );
    }
}
