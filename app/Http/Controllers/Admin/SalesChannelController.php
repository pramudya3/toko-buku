<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderSalesChannelRequest;
use App\Http\Requests\Admin\StoreSalesChannelRequest;
use App\Http\Requests\Admin\UpdateSalesChannelRequest;
use App\Models\SalesChannel;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SalesChannelController extends Controller
{
    /**
     * Simpan channel penjualan baru (custom).
     */
    public function store(StoreSalesChannelRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        SalesChannel::create([
            ...$validated,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => (int) SalesChannel::max('sort_order') + 1,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Sumber penjualan {$validated['name']} berhasil ditambahkan.",
        ]);

        ActivityLogger::log(ActivityAction::SettingsUpdate, "Sumber penjualan {$validated['name']} ditambahkan");

        return back();
    }

    /**
     * Update channel penjualan (nama / aktif-nonaktif).
     */
    public function update(UpdateSalesChannelRequest $request, SalesChannel $salesChannel): RedirectResponse
    {
        $validated = $request->validated();

        $salesChannel->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Sumber penjualan {$salesChannel->name} berhasil diperbarui.",
        ]);

        ActivityLogger::log(ActivityAction::SettingsUpdate, "Sumber penjualan {$salesChannel->name} diperbarui");

        return back();
    }

    /**
     * Aktifkan / nonaktifkan beberapa channel penjualan sekaligus.
     */
    public function bulkUpdate(ReorderSalesChannelRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $count = SalesChannel::whereIn('id', $validated['ids'])->update([
            'is_active' => $validated['is_active'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $validated['is_active']
                ? "{$count} sumber penjualan berhasil diaktifkan."
                : "{$count} sumber penjualan berhasil dinonaktifkan.",
        ]);

        ActivityLogger::log(
            ActivityAction::SettingsUpdate,
            $validated['is_active']
                ? "{$count} sumber penjualan diaktifkan (bulk)"
                : "{$count} sumber penjualan dinonaktifkan (bulk)"
        );

        return back();
    }

    /**
     * Hapus channel penjualan (soft delete).
     */
    public function destroy(SalesChannel $salesChannel): RedirectResponse
    {
        $salesChannel->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Sumber penjualan {$salesChannel->name} berhasil dihapus.",
            'undo' => ['url' => route('admin.settings.sumber-penjualan.restore', $salesChannel)],
        ]);

        ActivityLogger::log(ActivityAction::SettingsDelete, "Sumber penjualan {$salesChannel->name} dihapus");

        return back();
    }

    /**
     * Pulihkan channel penjualan yang dihapus (soft delete).
     */
    public function restore(SalesChannel $salesChannel): RedirectResponse
    {
        $salesChannel->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Sumber penjualan {$salesChannel->name} berhasil dipulihkan.",
        ]);

        ActivityLogger::log(ActivityAction::SettingsUpdate, "Sumber penjualan {$salesChannel->name} dipulihkan");

        return back();
    }
}
