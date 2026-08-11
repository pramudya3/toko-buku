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
            ->with('supplier:id,nama', 'items.book:id,judul,kode_sku')
            ->withCount('items')
            ->withSum('payments as paid', 'amount')
            ->when($request->filled('supplier_id'), fn ($query) => $query->where('supplier_id', $request->string('supplier_id')->toString()))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('purchase_date', '>=', $request->string('from')->toString()))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('purchase_date', '<=', $request->string('to')->toString()))
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/purchases/Index', [
            'purchases' => $purchases,
            'suppliers' => Supplier::orderBy('nama')->get(['id', 'nama']),
            'filters' => $request->only(['supplier_id', 'from', 'to']),
        ]);
    }

    /**
     * Nota pembelian dari supplier — halaman print (A4), tanpa layout admin.
     */
    public function invoice(SupplierPurchase $supplierPurchase): Response
    {
        $supplierPurchase->load([
            'supplier:id,nama,telepon,alamat',
            'items:id,supplier_purchase_id,book_id,qty,price,subtotal',
            'items.book:id,judul,kode_sku',
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
        $supplier = Supplier::findOrFail($validated->string('supplier_id')->toString());

        try {
            $purchase = $this->supplierService->recordPurchase(
                supplier: $supplier,
                refCode: $validated->string('ref_code')->toString(),
                purchaseDate: $validated->string('purchase_date')->toString(),
                items: $validated->input('items'),
                notes: $validated->string('notes')->toString() !== '' ? $validated->string('notes')->toString() : null,
                paidAmount: $validated->integer('paid_amount'),
                userId: $request->user()?->id,
                warehouseKode: $validated->string('warehouse_kode')->toString(),
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
     * Pilihan buku aktif untuk form pembelian.
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
}
