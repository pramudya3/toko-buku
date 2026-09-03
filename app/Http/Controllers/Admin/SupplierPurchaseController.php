<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupplierPurchaseRequest;
use App\Models\Book;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\SupplierPurchase;
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
 * Pencatatan barang datang dari supplier (pembelian) — menu terpisah dari
 * CRUD supplier. Stok masuk gudang utama + hutang bertambah (atau bayar
 * langsung via paid_amount).
 */
class SupplierPurchaseController extends Controller
{
    public function __construct(private readonly SupplierService $supplierService) {}

    /**
     * List pembelian + filter supplier & rentang tanggal.
     */
    public function index(Request $request): Response
    {
        $purchases = SupplierPurchase::query()
            ->with(['supplier:id,nama', 'warehouse:kode,nama', 'items.book:id,judul,kode_sku'])
            ->withCount('items')
            ->withSum('payments as paid', 'amount')
            ->when($request->filled('supplier_id'), fn ($query) => $query->where('supplier_id', $request->string('supplier_id')->toString()))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('purchase_date', '>=', $request->string('from')->toString()))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('purchase_date', '<=', $request->string('to')->toString()))
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return Inertia::render('admin/purchases/Index', [
            'purchases' => $purchases,
            'suppliers' => Supplier::orderBy('nama')->get(['id', 'nama']),
            'filters' => $request->only(['supplier_id', 'from', 'to']),
        ]);
    }

    /**
     * Detail pembelian — halaman baca saja.
     */
    public function show(SupplierPurchase $purchase): Response
    {
        $purchase->load([
            'supplier:id,nama,telepon,alamat',
            'warehouse:kode,nama',
            'items:id,supplier_purchase_id,book_id,book_edition_id,qty,price,subtotal,stock_before,hpp_old,hpp_new,landed_cost',
            'items.book:id,judul,kode_sku',
            'items.edition:id,cetakan_ke,harga_beli,harga_jual',
            'items.allocations:supplier_purchase_item_id,warehouse_kode,qty',
            'payments:id,supplier_purchase_id,amount,payment_date',
            'returns:id,supplier_purchase_id,total,return_date',
        ]);

        $resolvedKodes = $purchase->resolvedWarehouseKodes();
        $warehouses = Warehouse::query()
            ->when($resolvedKodes !== [], fn ($q) => $q->whereIn('kode', $resolvedKodes))
            ->orderBy('nama')
            ->get(['kode', 'nama']);

        // Fallback: load all sellable for mapping if resolved empty (legacy)
        if ($warehouses->isEmpty()) {
            $warehouses = Warehouse::query()->sellable()->orderBy('nama')->get(['kode', 'nama']);
        }

        return Inertia::render('admin/purchases/Show', [
            'purchase' => [
                'id' => $purchase->id,
                'ref_code' => $purchase->ref_code,
                'purchase_date' => $purchase->purchase_date?->format('Y-m-d') ?? (string) $purchase->purchase_date,
                'total' => $purchase->total,
                'shipping_cost' => $purchase->shipping_cost,
                'notes' => $purchase->notes,
                'warehouse_kode' => $purchase->warehouse_kode,
                'warehouse_kodes' => $purchase->resolvedWarehouseKodes(),
                'warehouse' => $purchase->warehouse ? ['kode' => $purchase->warehouse->kode, 'nama' => $purchase->warehouse->nama] : null,
                'warehouses' => $warehouses->map(fn ($w) => ['kode' => $w->kode, 'nama' => $w->nama])->values(),
                'supplier' => $purchase->supplier ? ['id' => $purchase->supplier->id, 'nama' => $purchase->supplier->nama, 'telepon' => $purchase->supplier->telepon, 'alamat' => $purchase->supplier->alamat] : null,
                'items' => $purchase->items->map(fn ($item) => [
                    'id' => $item->id,
                    'qty' => $item->qty,
                    'price' => $item->price,
                    'subtotal' => $item->subtotal,
                    'stock_before' => $item->stock_before,
                    'hpp_old' => $item->hpp_old,
                    'hpp_new' => $item->hpp_new,
                    'landed_cost' => $item->landed_cost,
                    'book' => $item->book ? ['id' => $item->book->id, 'judul' => $item->book->judul, 'kode_sku' => $item->book->kode_sku] : null,
                    'edition' => $item->edition ? ['id' => $item->edition->id, 'cetakan_ke' => $item->edition->cetakan_ke, 'harga_beli' => $item->edition->harga_beli] : null,
                    'allocations' => $item->allocations->map(fn ($a) => ['warehouse_kode' => $a->warehouse_kode, 'qty' => $a->qty])->values(),
                ])->values(),
                'payments' => $purchase->payments->map(fn ($p) => ['id' => $p->id, 'amount' => $p->amount, 'payment_date' => $p->payment_date?->format('Y-m-d') ?? (string) $p->payment_date])->values(),
                'returns' => $purchase->returns->map(fn ($r) => ['id' => $r->id, 'total' => $r->total, 'return_date' => $r->return_date?->format('Y-m-d') ?? (string) $r->return_date])->values(),
            ],
            'warehouses' => Warehouse::query()->sellable()->orderBy('nama')->get(['kode', 'nama'])->map(fn ($w) => ['kode' => $w->kode, 'nama' => $w->nama])->values(),
        ]);
    }

    /**
     * Nota pembelian dari supplier — halaman print (A4), tanpa layout admin.
     */
    public function invoice(SupplierPurchase $supplierPurchase): Response
    {
        $supplierPurchase->load([
            'supplier:id,nama,telepon,alamat',
            'items:id,supplier_purchase_id,book_id,book_edition_id,qty,price,subtotal',
            'items.book:id,judul,kode_sku',
            'items.edition:id,cetakan_ke,harga_beli',
            'items.allocations:supplier_purchase_item_id,warehouse_kode,qty',
        ]);

        return Inertia::render('print/purchases/Invoice', [
            'purchase' => $supplierPurchase,
            'store' => [
                'nama' => Setting::get('store_nama_lembaga') ?: config('app.name'),
                'logo_url' => Setting::get('store_logo_url', ''),
                'alamat' => Setting::get('store_alamat', ''),
                'npwp' => Setting::get('store_npwp', ''),
                'telepon' => Setting::get('store_telepon', ''),
                'email' => Setting::get('store_email', ''),
            ],
        ]);
    }

    /**
     * Form catat barang datang dari supplier.
     */
    public function create(): Response
    {
        return Inertia::render('admin/purchases/Create', [
            'suppliers' => Supplier::orderBy('nama')->get(['id', 'nama']),
            'warehouses' => Warehouse::query()
                ->sellable()
                ->orderBy('nama')
                ->get(['kode', 'nama']),
        ]);
    }

    /**
     * Simpan pembelian: stok masuk + hutang tercatat.
     */
    public function store(SupplierPurchaseRequest $request): RedirectResponse
    {
        $validated = $request->safe();
        $validatedArray = $validated->all();
        $supplier = Supplier::findOrFail($validated->string('supplier_id')->toString());

        $warehouseKodes = $validatedArray['warehouse_kodes'] ?? null;
        $warehouseKode = $validatedArray['warehouse_kode'] ?? null;
        if ($warehouseKode === '') {
            $warehouseKode = null;
        }
        $shippingCost = isset($validatedArray['shipping_cost']) ? (int) $validatedArray['shipping_cost'] : 0;

        try {
            $purchase = $this->supplierService->recordPurchase(
                supplier: $supplier,
                refCode: $validated->string('ref_code')->toString(),
                purchaseDate: $validated->string('purchase_date')->toString(),
                items: $validatedArray['items'],
                notes: $validated->string('notes')->toString() !== '' ? $validated->string('notes')->toString() : null,
                paidAmount: $validated->integer('paid_amount'),
                userId: $request->user()?->id,
                warehouseKode: $warehouseKode !== null ? (string) $warehouseKode : null,
                warehouseKodes: is_array($warehouseKodes) ? $warehouseKodes : null,
                shippingCost: $shippingCost,
            );
        } catch (\Throwable $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $e instanceof \RuntimeException ? $e->getMessage() : 'Gagal menyimpan pembelian.',
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Pembelian {$purchase->ref_code} senilai Rp ".number_format($purchase->total).' tercatat.',
        ]);

        ActivityLogger::log(
            ActivityAction::PurchaseCreate,
            "Barang masuk {$purchase->ref_code} dari {$supplier->nama} (Rp ".number_format($purchase->total).')',
            $purchase,
        );

        return to_route('admin.purchases.index');
    }

    /**
     * Pilihan buku aktif untuk form pembelian — sertakan cetakan untuk harga per cetakan.
     */
    public function bookOptions(Request $request): JsonResponse
    {
        $search = trim($request->string('search')->toString());

        $books = Book::query()
            ->where('aktif', true)
            ->with(['editions:id,book_id,cetakan_ke,harga_beli,harga_jual,is_active', 'editions.stocks'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereLike('judul', "%{$search}%")
                        ->orWhereLike('kode_sku', "%{$search}%");
                });
            })
            ->orderBy('judul')
            ->paginate(Pagination::perPage($request, 20))
            ->withQueryString();

        $books->getCollection()->transform(function (Book $book): array {
            return [
                'id' => $book->id,
                'judul' => $book->judul,
                'kode_sku' => $book->kode_sku,
                'harga' => $book->harga,
                'editions' => $book->editions->map(fn ($e) => [
                    'id' => $e->id,
                    'cetakan_ke' => $e->cetakan_ke,
                    'harga_beli' => $e->harga_beli,
                    'harga_jual' => $e->harga_jual,
                    'is_active' => $e->is_active,
                    'stok' => $e->stocks->sum('qty'),
                ])->values(),
            ];
        });

        return response()->json([
            'data' => $books->items(),
            'current_page' => $books->currentPage(),
            'last_page' => $books->lastPage(),
            'total' => $books->total(),
        ]);
    }
}
