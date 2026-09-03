<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkUpdateSupplierReturnReasonRequest;
use App\Http\Requests\Admin\StoreSupplierReturnReasonRequest;
use App\Http\Requests\Admin\UpdateSupplierReturnReasonRequest;
use App\Models\SupplierReturnReason;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SupplierReturnReasonController extends Controller
{
    /**
     * Halaman pengaturan alasan retur.
     */
    public function index(): Response
    {
        $reasons = SupplierReturnReason::orderBy('type')
            ->orderBy('name')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'category', 'type', 'is_active', 'sort_order']);

        return Inertia::render('admin/settings/AlasanRetur', [
            'reasons' => $reasons,
        ]);
    }

    /**
     * Simpan alasan baru.
     */
    public function store(StoreSupplierReturnReasonRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        SupplierReturnReason::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'category' => $validated['category'],
            'type' => $validated['type'] ?? 'supplier',
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Alasan retur ditambahkan.']);
    }

    /**
     * Update alasan.
     */
    public function update(UpdateSupplierReturnReasonRequest $request, SupplierReturnReason $alasanRetur): RedirectResponse
    {
        $validated = $request->validated();

        $alasanRetur->update($validated);

        return back()->with('toast', ['type' => 'success', 'message' => 'Alasan retur diperbarui.']);
    }

    /**
     * Hapus alasan.
     */
    public function destroy(SupplierReturnReason $alasanRetur): RedirectResponse
    {
        $alasanRetur->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Alasan retur dihapus.']);
    }

    /**
     * Toggle aktif.
     */
    public function toggle(SupplierReturnReason $alasanRetur): RedirectResponse
    {
        $alasanRetur->update(['is_active' => ! $alasanRetur->is_active]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Status alasan diperbarui.']);
    }

    /**
     * Bulk update — dukung sort_order (drag) & is_active (checkbox bulk).
     */
    public function bulkUpdate(BulkUpdateSupplierReturnReasonRequest $request): RedirectResponse
    {
        // Bulk toggle aktif/nonaktif via ids + is_active (dari checkbox select all)
        if ($request->has('ids')) {
            $validated = $request->validated();
            SupplierReturnReason::whereIn('id', $validated['ids'])
                ->update(['is_active' => $validated['is_active']]);

            return back()->with('toast', ['type' => 'success', 'message' => 'Status diperbarui.']);
        }

        $validated = $request->validated();

        foreach ($validated['reasons'] as $row) {
            SupplierReturnReason::where('id', $row['id'])->update(['sort_order' => $row['sort_order']]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Urutan disimpan.']);
    }
}
