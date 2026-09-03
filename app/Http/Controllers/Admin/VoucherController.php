<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VoucherRequest;
use App\Models\Voucher;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class VoucherController extends Controller
{
    /**
     * List voucher + jumlah pemakaian (VOUCHER-01).
     */
    public function index(Request $request): Response
    {
        $vouchers = Voucher::query()
            ->withCount('usages')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->where(function ($query) use ($request): void {
                    $query->whereLike('nama', '%'.$request->string('search')->toString().'%')
                        ->orWhereLike('kode', '%'.$request->string('search')->toString().'%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return Inertia::render('admin/vouchers/Index', [
            'vouchers' => $vouchers,
            'filters' => $request->only(['search']),
            'typeOptions' => VoucherType::options(),
            'scopeOptions' => VoucherScope::options(),
        ]);
    }

    /**
     * Form buat voucher.
     */
    public function create(): Response
    {
        return Inertia::render('admin/vouchers/Form', [
            'voucher' => null,
            'typeOptions' => VoucherType::options(),
            'scopeOptions' => VoucherScope::options(),
        ]);
    }

    /**
     * Simpan voucher (VOUCHER-01).
     */
    public function store(VoucherRequest $request): RedirectResponse
    {
        $voucher = Voucher::create($this->payload($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Voucher {$voucher->nama} berhasil dibuat."]);

        return to_route('admin.vouchers.index');
    }

    /**
     * Form edit voucher.
     */
    public function edit(Voucher $voucher): Response
    {
        return Inertia::render('admin/vouchers/Form', [
            'voucher' => $voucher,
            'typeOptions' => VoucherType::options(),
            'scopeOptions' => VoucherScope::options(),
        ]);
    }

    /**
     * Update voucher (VOUCHER-01).
     */
    public function update(VoucherRequest $request, Voucher $voucher): RedirectResponse
    {
        try {
            $this->assertNotUsed($voucher, 'diubah');

            $voucher->update($this->payload($request));
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return to_route('admin.vouchers.index');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Voucher {$voucher->nama} berhasil diperbarui."]);

        return to_route('admin.vouchers.index');
    }

    /**
     * Hapus voucher (soft delete).
     */
    public function destroy(Voucher $voucher): RedirectResponse
    {
        try {
            $this->assertNotUsed($voucher, 'dihapus');

            $voucher->delete();
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Voucher {$voucher->nama} berhasil dihapus.",
            'undo' => ['url' => route('admin.vouchers.restore', $voucher)],
        ]);

        return to_route('admin.vouchers.index');
    }

    /**
     * Pulihkan voucher yang dihapus (soft delete).
     */
    public function restore(Voucher $voucher): RedirectResponse
    {
        $voucher->restore();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Voucher {$voucher->nama} berhasil dipulihkan.",
        ]);

        return to_route('admin.vouchers.index');
    }

    /**
     * Toggle aktif/nonaktif cepat.
     */
    public function toggle(Voucher $voucher): RedirectResponse
    {
        try {
            $this->assertNotUsed($voucher, 'dinonaktifkan');

            $voucher->update(['is_active' => ! $voucher->is_active]);
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Voucher {$voucher->nama} di-".($voucher->is_active ? 'aktifkan' : 'nonaktifkan').'.',
        ]);

        return back();
    }

    /**
     * Voucher yang sudah dipakai di pesanan tidak boleh diubah, dihapus,
     * atau dinonaktifkan — riwayat order harus tetap konsisten.
     */
    private function assertNotUsed(Voucher $voucher, string $tindakan): void
    {
        if ($voucher->usages()->exists()) {
            throw new RuntimeException("Voucher '{$voucher->nama}' sudah dipakai di pesanan — tidak bisa {$tindakan}.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(VoucherRequest $request): array
    {
        $data = $request->validated();

        // Kode opsional — otomatis dibuat dari nama bila dikosongkan.
        if (blank($data['kode'] ?? null)) {
            $data['kode'] = $this->generateKode($request->string('nama')->toString());
        } else {
            $data['kode'] = Str::upper($data['kode']);
        }

        // Minimal belanja opsional — kosong/null dinormalisasi ke 0 agar
        // tidak menabrak NOT NULL constraint (default kolom hanya berlaku
        // bila kolom di-omit dari INSERT).
        $data['min_order_amount'] = (int) ($data['min_order_amount'] ?? 0);

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        return $data;
    }

    private function generateKode(string $nama): string
    {
        $base = Str::upper(Str::slug($nama, '-')) ?: 'VOUCHER';

        $kode = $base;
        $suffix = 1;

        while (Voucher::withTrashed()->where('kode', $kode)->exists()) {
            $kode = $base.'-'.$suffix;
            $suffix++;
        }

        return $kode;
    }
}
