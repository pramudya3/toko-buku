<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BankAccountRequest;
use App\Models\BankAccount;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    /**
     * Halaman rekening bank (bagian dari Pengaturan).
     */
    public function index(): Response
    {
        $accounts = BankAccount::query()
            ->orderBy('sort_order')
            ->orderBy('bank_name')
            ->get();

        return Inertia::render('admin/settings/Rekening', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Simpan rekening bank baru.
     */
    public function store(BankAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $account = BankAccount::create([
            ...$validated,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => (int) BankAccount::max('sort_order') + 1,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Rekening {$account->bank_name} berhasil ditambahkan.",
        ]);

        ActivityLogger::log(ActivityAction::SettingsUpdate, "Rekening bank {$account->bank_name} ({$account->account_number}) ditambahkan");

        return back();
    }

    /**
     * Update rekening bank.
     */
    public function update(BankAccountRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        $validated = $request->validated();

        $bankAccount->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Rekening {$bankAccount->bank_name} berhasil diperbarui.",
        ]);

        ActivityLogger::log(ActivityAction::SettingsUpdate, "Rekening bank {$bankAccount->bank_name} diperbarui");

        return back();
    }

    /**
     * Hapus rekening bank (soft delete).
     */
    public function destroy(BankAccount $bankAccount): RedirectResponse
    {
        $bankAccount->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Rekening {$bankAccount->bank_name} berhasil dihapus.",
            'undo' => ['url' => route('admin.settings.rekening.restore', $bankAccount)],
        ]);

        ActivityLogger::log(ActivityAction::SettingsDelete, "Rekening bank {$bankAccount->bank_name} dihapus");

        return back();
    }

    /**
     * Pulihkan rekening bank yang dihapus (soft delete).
     */
    public function restore(BankAccount $bankAccount): RedirectResponse
    {
        $bankAccount->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Rekening {$bankAccount->bank_name} berhasil dipulihkan.",
        ]);

        ActivityLogger::log(ActivityAction::SettingsUpdate, "Rekening bank {$bankAccount->bank_name} dipulihkan");

        return back();
    }
}
