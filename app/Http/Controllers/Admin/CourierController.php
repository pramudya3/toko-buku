<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CourierBulkUpdateRequest;
use App\Http\Requests\Admin\CourierStoreRequest;
use App\Http\Requests\Admin\CourierUpdateRequest;
use App\Models\Courier;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CourierController extends Controller
{
    /**
     * Simpan ekspedisi baru (custom).
     */
    public function store(CourierStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Courier::create([
            ...$validated,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => (int) Courier::max('sort_order') + 1,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Ekspedisi {$validated['name']} berhasil ditambahkan.",
        ]);

        ActivityLogger::log(ActivityAction::SettingsUpdate, "Ekspedisi {$validated['name']} ditambahkan");

        return back();
    }

    /**
     * Update ekspedisi (nama / aktif-nonaktif).
     */
    public function update(CourierUpdateRequest $request, Courier $courier): RedirectResponse
    {
        $validated = $request->validated();

        $courier->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Ekspedisi {$courier->name} berhasil diperbarui.",
        ]);

        ActivityLogger::log(ActivityAction::SettingsUpdate, "Ekspedisi {$courier->name} diperbarui");

        return back();
    }

    /**
     * Aktifkan / nonaktifkan beberapa ekspedisi sekaligus.
     */
    public function bulkUpdate(CourierBulkUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $count = Courier::whereIn('id', $validated['ids'])->update([
            'is_active' => $validated['is_active'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $validated['is_active']
                ? "{$count} ekspedisi berhasil diaktifkan."
                : "{$count} ekspedisi berhasil dinonaktifkan.",
        ]);

        ActivityLogger::log(
            ActivityAction::SettingsUpdate,
            $validated['is_active']
                ? "{$count} ekspedisi diaktifkan (bulk)"
                : "{$count} ekspedisi dinonaktifkan (bulk)"
        );

        return back();
    }

    /**
     * Hapus ekspedisi (soft delete).
     */
    public function destroy(Courier $courier): RedirectResponse
    {
        $courier->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Ekspedisi {$courier->name} berhasil dihapus.",
            'undo' => ['url' => route('admin.settings.ekspedisi.restore', $courier)],
        ]);

        ActivityLogger::log(ActivityAction::SettingsDelete, "Ekspedisi {$courier->name} dihapus");

        return back();
    }

    /**
     * Pulihkan ekspedisi yang dihapus (soft delete).
     */
    public function restore(Courier $courier): RedirectResponse
    {
        $courier->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Ekspedisi {$courier->name} berhasil dipulihkan.",
        ]);

        ActivityLogger::log(ActivityAction::SettingsUpdate, "Ekspedisi {$courier->name} dipulihkan");

        return back();
    }
}
