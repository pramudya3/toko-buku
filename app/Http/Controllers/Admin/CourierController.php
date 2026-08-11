<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CourierController extends Controller
{
    /**
     * Simpan ekspedisi baru (custom).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/', Rule::unique('couriers', 'code')->withoutTrashed()],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

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
    public function update(Request $request, Courier $courier): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $courier->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Ekspedisi {$courier->name} berhasil diperbarui.",
        ]);

        ActivityLogger::log(ActivityAction::SettingsUpdate, "Ekspedisi {$courier->name} diperbarui");

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
