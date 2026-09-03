<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupplierRequest;
use App\Models\Supplier;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD master supplier. Transaksi (pembelian, retur, hutang, laporan)
 * ditangani controller terpisah: SupplierPurchaseController,
 * SupplierReturnController, SupplierDebtController, SupplierReportController.
 */
class SupplierController extends Controller
{
    /**
     * Daftar supplier + saldo hutang.
     */
    public function index(Request $request): Response
    {
        $suppliers = Supplier::query()
            ->withSum('purchases as purchase_total', 'total')
            ->withSum('returns as return_total', 'total')
            ->withSum('payments as payment_total', 'amount')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->whereLike('nama', "%{$search}%")
                        ->orWhereLike('telepon', "%{$search}%");
                });
            })
            ->orderBy('nama')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        $suppliers->getCollection()->transform(function (Supplier $supplier): Supplier {
            // withSum menghasilkan null bila tidak ada transaksi — normalisasi ke int.
            $supplier->purchase_total = (int) ($supplier->purchase_total ?? 0);
            $supplier->return_total = (int) ($supplier->return_total ?? 0);
            $supplier->payment_total = (int) ($supplier->payment_total ?? 0);
            $supplier->saldo_hutang = $supplier->purchase_total - $supplier->return_total - $supplier->payment_total;

            return $supplier;
        });

        return Inertia::render('admin/suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Form buat supplier baru.
     */
    public function create(): Response
    {
        return Inertia::render('admin/suppliers/Form', [
            'supplier' => null,
        ]);
    }

    /**
     * Simpan supplier baru.
     */
    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->safe()->only(['nama', 'telepon', 'alamat', 'catatan']));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Supplier {$supplier->nama} berhasil dibuat."]);

        return to_route('admin.suppliers.index');
    }

    /**
     * Form edit supplier.
     */
    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('admin/suppliers/Form', [
            'supplier' => $supplier,
        ]);
    }

    /**
     * Update supplier.
     */
    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->safe()->only(['nama', 'telepon', 'alamat', 'catatan']));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Supplier {$supplier->nama} berhasil diperbarui."]);

        return to_route('admin.suppliers.index');
    }

    /**
     * Hapus supplier — diblokir bila masih punya riwayat transaksi.
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $hasTransactions = $supplier->purchases()->exists()
            || $supplier->returns()->exists()
            || $supplier->payments()->exists();

        if ($hasTransactions) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Supplier {$supplier->nama} memiliki riwayat transaksi dan tidak dapat dihapus.",
            ]);

            return back();
        }

        $supplier->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Supplier {$supplier->nama} berhasil dihapus.",
            'undo' => ['url' => route('admin.suppliers.restore', $supplier)],
        ]);

        return back();
    }

    public function restore(Supplier $supplier): RedirectResponse
    {
        $supplier->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Supplier {$supplier->nama} berhasil dipulihkan.",
        ]);

        return to_route('admin.suppliers.index');
    }
}
