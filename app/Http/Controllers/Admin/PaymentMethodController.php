<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PaymentMethodController extends Controller
{
    /**
     * Simpan metode pembayaran baru (custom).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/', Rule::unique('payment_methods', 'code')->withoutTrashed()],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        PaymentMethod::create([
            ...$validated,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => (int) PaymentMethod::max('sort_order') + 1,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Metode pembayaran {$validated['name']} berhasil ditambahkan.",
        ]);

        ActivityLogger::log(ActivityAction::SettingsUpdate, "Metode pembayaran {$validated['name']} ditambahkan");

        return back();
    }

    /**
     * Update metode pembayaran (nama / aktif-nonaktif).
     */
    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $paymentMethod->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Metode pembayaran {$paymentMethod->name} berhasil diperbarui.",
        ]);

        ActivityLogger::log(ActivityAction::SettingsUpdate, "Metode pembayaran {$paymentMethod->name} diperbarui");

        return back();
    }

    /**
     * Aktifkan / nonaktifkan beberapa metode pembayaran sekaligus.
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string', Rule::exists('payment_methods', 'id')],
            'is_active' => ['required', 'boolean'],
        ]);

        $count = PaymentMethod::whereIn('id', $validated['ids'])->update([
            'is_active' => $validated['is_active'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $validated['is_active']
                ? "{$count} metode pembayaran berhasil diaktifkan."
                : "{$count} metode pembayaran berhasil dinonaktifkan.",
        ]);

        ActivityLogger::log(
            ActivityAction::SettingsUpdate,
            $validated['is_active']
                ? "{$count} metode pembayaran diaktifkan (bulk)"
                : "{$count} metode pembayaran dinonaktifkan (bulk)"
        );

        return back();
    }

    /**
     * Hapus metode pembayaran (soft delete).
     */
    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Metode pembayaran {$paymentMethod->name} berhasil dihapus.",
            'undo' => ['url' => route('admin.settings.pembayaran.restore', $paymentMethod)],
        ]);

        ActivityLogger::log(ActivityAction::SettingsDelete, "Metode pembayaran {$paymentMethod->name} dihapus");

        return back();
    }

    /**
     * Pulihkan metode pembayaran yang dihapus (soft delete).
     */
    public function restore(PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Metode pembayaran {$paymentMethod->name} berhasil dipulihkan.",
        ]);

        ActivityLogger::log(ActivityAction::SettingsUpdate, "Metode pembayaran {$paymentMethod->name} dipulihkan");

        return back();
    }
}
