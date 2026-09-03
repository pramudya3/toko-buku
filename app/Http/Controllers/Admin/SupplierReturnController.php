<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupplierReturnRequest;
use App\Models\Book;
use App\Models\Supplier;
use App\Models\SupplierPurchase;
use App\Models\SupplierReturn;
use App\Models\SupplierReturnReason;
use App\Models\Warehouse;
use App\Services\SupplierService;
use App\Support\ActivityLogger;
use App\Support\Pagination;
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
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return Inertia::render('admin/supplier-returns/Index', [
            'returns' => $returns,
            'suppliers' => Supplier::orderBy('nama')->get(['id', 'nama']),
            'filters' => $request->only(['supplier_id', 'from', 'to']),
        ]);
    }

    /**
     * Form catat retur ke supplier — support prefill dari Barang Masuk (purchase_id).
     */
    public function create(Request $request): Response
    {
        $prefilledPurchase = null;
        $prefilledSupplierId = $request->string('supplier_id')->toString() ?: null;
        $prefilledPurchaseId = $request->string('purchase_id')->toString() ?: null;

        if ($prefilledPurchaseId) {
            $prefilledPurchase = SupplierPurchase::with(['supplier:id,nama', 'warehouse:kode,nama', 'items.book:id,judul,kode_sku', 'items.edition:id,cetakan_ke', 'items.allocations'])
                ->find($prefilledPurchaseId);
            if ($prefilledPurchase) {
                $prefilledSupplierId = (string) $prefilledPurchase->supplier_id;
            }
        }

        $warehouses = Warehouse::query()->sellable()->orderBy('nama')->get(['kode', 'nama']);

        return Inertia::render('admin/supplier-returns/Create', [
            'suppliers' => Supplier::orderBy('nama')->get(['id', 'nama']),
            'warehouses' => $warehouses->map(fn ($w) => ['kode' => $w->kode, 'nama' => $w->nama])->values(),
            'prefilledSupplierId' => $prefilledSupplierId,
            'prefilledPurchaseId' => $prefilledPurchaseId,
            'prefilledPurchase' => $prefilledPurchase ? [
                'id' => $prefilledPurchase->id,
                'ref_code' => $prefilledPurchase->ref_code,
                'purchase_date' => $prefilledPurchase->purchase_date?->format('Y-m-d') ?? (string) $prefilledPurchase->purchase_date,
                'warehouse_kode' => $prefilledPurchase->warehouse_kode,
                'warehouse_kodes' => $prefilledPurchase->resolvedWarehouseKodes(),
                'warehouse_nama' => $prefilledPurchase->warehouse?->nama,
                'items' => $prefilledPurchase->items->map(fn ($i) => [
                    'book_id' => $i->book_id,
                    'book_edition_id' => $i->book_edition_id,
                    'judul' => $i->book->judul,
                    'qty' => $i->qty,
                    'price' => $i->price,
                    'cetakan_ke' => $i->edition?->cetakan_ke,
                    'allocations' => $i->allocations->map(fn ($a) => ['warehouse_kode' => $a->warehouse_kode, 'qty' => $a->qty])->values(),
                ]),
            ] : null,
            'returnReasons' => SupplierReturnReason::where('is_active', true)->where('type', 'supplier')->orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name', 'category', 'type']),
        ]);
    }

    /**
     * Simpan retur: stok (defect/normal) berkurang + hutang berkurang.
     * Alasan & sumber stok diambil dari field global (Informasi Retur) jika per-item tidak ada.
     */
    public function store(SupplierReturnRequest $request): RedirectResponse
    {
        $validated = $request->safe();
        $validatedArray = $validated->all();
        $supplier = Supplier::findOrFail($validated->string('supplier_id')->toString());

        $items = $validatedArray['items'] ?? [];
        $globalReason = $validatedArray['reason'] ?? null;
        $globalSource = $validatedArray['source'] ?? null;
        $shippingCost = isset($validatedArray['shipping_cost']) ? (int) $validatedArray['shipping_cost'] : 0;
        // Jika reason/source global ada, terapkan ke tiap item yang belum punya
        if ($globalReason !== null || $globalSource !== null) {
            foreach ($items as &$it) {
                if (empty($it['reason']) && $globalReason) {
                    $it['reason'] = $globalReason;
                }
                if (empty($it['source']) && $globalSource) {
                    $it['source'] = $globalSource;
                }
            }
            unset($it);
        }

        try {
            $return = $this->supplierService->recordReturn(
                supplier: $supplier,
                returnDate: $validated->string('return_date')->toString(),
                items: $items,
                purchaseId: $validated->string('purchase_id')->toString() ?: null,
                notes: $validated->string('notes')->toString() !== '' ? $validated->string('notes')->toString() : null,
                userId: $request->user()?->id,
                shippingCost: $shippingCost,
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
     * Pilihan buku untuk retur — jika purchase_id terisi, hanya buku di faktur tersebut dengan sisa >0.
     */
    public function bookOptions(Request $request): JsonResponse
    {
        $search = trim($request->string('search')->toString());
        $purchaseId = $request->string('purchase_id')->toString() ?: null;

        if ($purchaseId) {
            $purchase = SupplierPurchase::with(['items.book', 'items.edition', 'returns.items'])->find($purchaseId);
            if ($purchase) {
                $returnedPerKey = [];
                foreach ($purchase->returns as $ret) {
                    foreach ($ret->items as $rItem) {
                        $k = $rItem->book_id.':'.($rItem->book_edition_id ?? 'null');
                        $returnedPerKey[$k] = ($returnedPerKey[$k] ?? 0) + $rItem->qty;
                    }
                }
                $filtered = $purchase->items->filter(function ($item) use ($returnedPerKey, $search): bool {
                    $key = $item->book_id.':'.($item->book_edition_id ?? 'null');
                    $remaining = max(0, $item->qty - ($returnedPerKey[$key] ?? 0));
                    if ($remaining <= 0) {
                        return false;
                    }
                    if ($search !== '' && ! str_contains(strtolower($item->book->judul ?? ''), strtolower($search)) && ! str_contains(strtolower($item->book->kode_sku ?? ''), strtolower($search))) {
                        return false;
                    }

                    return true;
                })->values();

                $data = $filtered->map(function ($item) use ($returnedPerKey): array {
                    $key = $item->book_id.':'.($item->book_edition_id ?? 'null');
                    $remaining = max(0, $item->qty - ($returnedPerKey[$key] ?? 0));

                    return [
                        'id' => $item->book_id,
                        'judul' => $item->book->judul,
                        'kode_sku' => $item->book->kode_sku,
                        'book_edition_id' => $item->book_edition_id,
                        'cetakan_ke' => $item->edition?->cetakan_ke,
                        'price' => $item->price,
                        'qty' => $item->qty,
                        'remaining' => $remaining,
                        'stock_defect' => 0,
                        'stock_normal' => 0,
                    ];
                });

                // Manual paginate (kecil, jadi 1 page)
                return response()->json([
                    'data' => $data,
                    'current_page' => 1,
                    'last_page' => 1,
                    'total' => $data->count(),
                ]);
            }
        }

        $books = Book::query()
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
            ->paginate(Pagination::perPage($request, 20))
            ->withQueryString();

        $books->getCollection()->transform(function (Book $book): array {
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
        });

        return response()->json([
            'data' => $books->items(),
            'current_page' => $books->currentPage(),
            'last_page' => $books->lastPage(),
            'total' => $books->total(),
        ]);
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
                ->with('warehouse:kode,nama')
                ->orderByDesc('purchase_date')
                ->orderByDesc('id')
                ->get(['id', 'ref_code', 'purchase_date', 'warehouse_kode', 'warehouse_kodes'])
                ->map(fn (SupplierPurchase $purchase): array => [
                    'id' => $purchase->id,
                    'ref_code' => $purchase->ref_code,
                    'purchase_date' => $purchase->purchase_date,
                    'warehouse_kode' => $purchase->warehouse_kode,
                    'warehouse_kodes' => $purchase->resolvedWarehouseKodes(),
                    'warehouse_nama' => $purchase->warehouse?->nama ?? '',
                ]),
        );
    }

    /**
     * Baris item faktur (untuk prefill harga & batas qty retur).
     * Mengembalikan sisa qty yang masih bisa diretur per item (qty beli - qty sudah diretur) + alokasi gudang.
     */
    public function purchaseDetail(SupplierPurchase $supplierPurchase): JsonResponse
    {
        $supplierPurchase->load(['items.book:id,judul', 'items.edition:id,cetakan_ke', 'items.allocations', 'returns.items', 'returns.items.allocations']);
        $returnedPerKey = [];
        $returnedPerWarehouse = [];
        foreach ($supplierPurchase->returns as $ret) {
            foreach ($ret->items as $rItem) {
                $key = $rItem->book_id.':'.($rItem->book_edition_id ?? 'null');
                $returnedPerKey[$key] = ($returnedPerKey[$key] ?? 0) + $rItem->qty;
                foreach ($rItem->allocations as $alloc) {
                    $wk = $alloc->warehouse_kode.':'.$key;
                    $returnedPerWarehouse[$wk] = ($returnedPerWarehouse[$wk] ?? 0) + $alloc->qty;
                }
            }
        }

        return response()->json(
            $supplierPurchase->items->map(function ($item) use ($returnedPerKey, $returnedPerWarehouse): array {
                $key = $item->book_id.':'.($item->book_edition_id ?? 'null');
                $alreadyReturned = $returnedPerKey[$key] ?? 0;
                $remaining = max(0, $item->qty - $alreadyReturned);
                $allocations = [];
                foreach ($item->allocations as $alloc) {
                    $wk = $alloc->warehouse_kode;
                    $allocKey = $wk.':'.$key;
                    $alreadyForWarehouse = $returnedPerWarehouse[$allocKey] ?? 0;
                    $remainingForWarehouse = max(0, $alloc->qty - $alreadyForWarehouse);
                    $allocations[$wk] = $remainingForWarehouse;
                }
                // Fallback jika tidak ada allocations (single gudang lama)
                if (empty($allocations) && $item->qty > 0) {
                    $allocations[$item->purchase->warehouse_kode ?? 'default'] = $remaining;
                }

                return [
                    'book_id' => $item->book_id,
                    'book_edition_id' => $item->book_edition_id,
                    'judul' => $item->book->judul,
                    'cetakan_ke' => $item->edition?->cetakan_ke,
                    'qty' => $item->qty,
                    'remaining' => $remaining,
                    'price' => $item->price,
                    'allocations' => $allocations,
                ];
            })->values(),
        );
    }
}
