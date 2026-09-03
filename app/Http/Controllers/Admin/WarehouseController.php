<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WarehouseRequest;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    /**
     * Daftar gudang.
     */
    public function index(Request $request): Response
    {
        $warehouses = Warehouse::query()
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query->whereLike('nama', "%{$search}%")
                        ->orWhereLike('kode', "%{$search}%");
                });
            })
            ->orderByDesc('is_defect')
            ->orderBy('nama')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return Inertia::render('admin/warehouses/Index', [
            'warehouses' => $warehouses,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Form buat gudang baru.
     */
    public function create(): Response
    {
        return Inertia::render('admin/warehouses/Form', [
            'warehouse' => null,
        ]);
    }

    /**
     * Simpan gudang baru.
     */
    public function store(WarehouseRequest $request): RedirectResponse
    {
        $warehouse = Warehouse::create($request->safe()->only(['kode', 'nama', 'alamat', 'is_defect', 'is_active']));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Gudang {$warehouse->nama} berhasil dibuat."]);

        return to_route('admin.warehouses.index');
    }

    /**
     * Form edit gudang.
     */
    public function edit(Warehouse $warehouse): Response
    {
        return Inertia::render('admin/warehouses/Form', [
            'warehouse' => $warehouse,
        ]);
    }

    /**
     * Update gudang (kode tidak bisa diubah).
     */
    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $data = $request->safe()->only(['nama', 'alamat', 'is_defect', 'is_active']);
        $warehouse->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Gudang {$warehouse->nama} berhasil diperbarui."]);

        return to_route('admin.warehouses.index');
    }

    /**
     * Hapus gudang — diblokir bila masih menyimpan stok / punya mutasi.
     */
    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $hasStock = $warehouse->stocks()->where('qty', '>', 0)->exists();
        $hasMovements = InventoryMovement::query()
            ->where('from_warehouse_id', $warehouse->id)
            ->orWhere('to_warehouse_id', $warehouse->id)
            ->exists();

        if ($hasStock || $hasMovements) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Gudang {$warehouse->nama} masih menyimpan stok / punya riwayat mutasi dan tidak dapat dihapus.",
            ]);

            return back();
        }

        // Baris stok kosong ikut dihapus agar tidak tersisa relasi.
        $warehouse->stocks()->delete();
        $warehouse->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Gudang {$warehouse->nama} berhasil dihapus.",
            'undo' => ['url' => route('admin.warehouses.restore', $warehouse)],
        ]);

        return back();
    }

    public function restore(Warehouse $warehouse): RedirectResponse
    {
        $warehouse->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Gudang {$warehouse->nama} berhasil dipulihkan.",
        ]);

        return to_route('admin.warehouses.index');
    }
}
