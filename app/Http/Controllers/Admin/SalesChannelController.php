<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\SalesChannel;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SalesChannelController extends Controller
{
    /**
     * Simpan channel penjualan baru (custom).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/', Rule::unique('sales_channels', 'code')->withoutTrashed()],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

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
    public function update(Request $request, SalesChannel $salesChannel): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

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
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string', Rule::exists('sales_channels', 'id')],
            'is_active' => ['required', 'boolean'],
        ]);

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
