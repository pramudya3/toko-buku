<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Piutang pelanggan — catat tagihan yang belum dibayar penuh + pembayaran cicilan.
 */
class ReceivableController extends Controller
{
    /**
     * Daftar piutang + filter status & pencarian pelanggan.
     */
    public function index(Request $request): Response
    {
        $receivables = Receivable::query()
            ->with(['customer:id,name,whatsapp_number', 'order:id,no_order'])
            ->withCount('payments')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->whereHas('customer', fn ($q) => $q->whereLike('name', "%{$search}%"));
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $status = $request->string('status')->toString();

                if ($status === 'lunas') {
                    $query->whereColumn('paid_amount', '>=', 'amount');
                } elseif ($status === 'belum_lunas') {
                    $query->whereColumn('paid_amount', '<', 'amount');
                }
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('admin/receivables/Index', [
            'receivables' => $receivables,
            'customers' => User::query()
                ->where('is_admin', false)
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'whatsapp_number']),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Catat piutang baru.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'string', 'exists:users,id'],
            'order_id' => ['nullable', 'string', 'exists:orders,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $receivable = Receivable::create([
            'customer_id' => (string) $validated['customer_id'],
            'order_id' => ! empty($validated['order_id']) ? (string) $validated['order_id'] : null,
            'amount' => (int) $validated['amount'],
            'due_date' => $validated['due_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Piutang Rp '.number_format($receivable->amount, 0, ',', '.').' berhasil dicatat.',
        ]);

        return back();
    }

    /**
     * Catat pembayaran piutang (cicilan / pelunasan).
     */
    public function pay(Request $request, Receivable $receivable): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'metode' => ['required', 'string', 'in:transfer,cod,cash'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $paymentAmount = (int) $validated['amount'];
        $remaining = $receivable->remaining();

        if ($paymentAmount > $remaining) {
            return back()->withErrors([
                'amount' => 'Pembayaran melebihi sisa piutang (sisa Rp '.number_format($remaining, 0, ',', '.').').',
            ]);
        }

        $payment = ReceivablePayment::create([
            'receivable_id' => $receivable->id,
            'paid_at' => $validated['paid_at'],
            'amount' => $paymentAmount,
            'metode' => $validated['metode'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $receivable->increment('paid_amount', $paymentAmount);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pembayaran Rp '.number_format($payment->amount, 0, ',', '.').' dicatat'
                .($receivable->isPaidOff() ? ' — piutang lunas.' : '.'),
        ]);

        ActivityLogger::log(
            ActivityAction::ReceivablePay,
            'Pembayaran piutang Rp '.number_format($payment->amount, 0, ',', '.').($receivable->isPaidOff() ? ' (lunas)' : ''),
            $receivable,
            ['amount' => $payment->amount, 'metode' => $validated['metode']],
        );

        return back();
    }

    /**
     * Hapus piutang (misal salah catat) — hanya bila belum ada pembayaran.
     */
    public function destroy(Receivable $receivable): RedirectResponse
    {
        if ($receivable->payments()->exists()) {
            throw new RuntimeException('Piutang dengan riwayat pembayaran tidak dapat dihapus.');
        }

        $receivable->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Piutang berhasil dihapus.',
        ]);

        return back();
    }
}
