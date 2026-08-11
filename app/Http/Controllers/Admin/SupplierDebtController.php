<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SupplierPaymentRequest;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierPurchase;
use App\Services\SupplierService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Hutang ke supplier: ringkasan saldo per supplier, daftar faktur belum
 * lunas, dan pencatatan pembayaran hutang.
 */
class SupplierDebtController extends Controller
{
    public function __construct(private readonly SupplierService $supplierService) {}

    /**
     * Ringkasan hutang + faktur belum lunas + riwayat pembayaran.
     */
    public function index(): Response
    {
        $suppliers = Supplier::query()
            ->withSum('purchases as purchase_total', 'total')
            ->withSum('returns as return_total', 'total')
            ->withSum('payments as payment_total', 'amount')
            ->orderBy('nama')
            ->get()
            ->map(function (Supplier $supplier): array {
                return [
                    'id' => $supplier->id,
                    'nama' => $supplier->nama,
                    'purchase_total' => (int) $supplier->purchase_total,
                    'return_total' => (int) $supplier->return_total,
                    'payment_total' => (int) $supplier->payment_total,
                    'saldo_hutang' => (int) $supplier->purchase_total - (int) $supplier->return_total - (int) $supplier->payment_total,
                ];
            })
            ->filter(fn (array $supplier): bool => $supplier['saldo_hutang'] > 0)
            ->values();

        $invoices = SupplierPurchase::query()
            ->with('supplier:id,nama')
            ->withSum('payments as paid', 'amount')
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (SupplierPurchase $purchase): array {
                $sisa = $this->supplierService->sisaPerFaktur($purchase);

                return [
                    'id' => $purchase->id,
                    'ref_code' => $purchase->ref_code,
                    'purchase_date' => $purchase->purchase_date,
                    'supplier_id' => $purchase->supplier_id,
                    'supplier_nama' => $purchase->supplier->nama,
                    'total' => $purchase->total,
                    'paid' => (int) $purchase->paid,
                    'sisa' => $sisa,
                ];
            })
            ->filter(fn (array $invoice): bool => $invoice['sisa'] > 0)
            ->values();

        $payments = SupplierPayment::query()
            ->with('supplier:id,nama', 'purchase:id,ref_code')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return Inertia::render('admin/supplier-debts/Index', [
            'suppliers' => $suppliers,
            'invoices' => $invoices,
            'payments' => $payments,
        ]);
    }

    /**
     * Catat pembayaran hutang ke supplier.
     */
    public function store(SupplierPaymentRequest $request): RedirectResponse
    {
        $validated = $request->safe();
        $supplier = Supplier::findOrFail($validated->string('supplier_id')->toString());

        try {
            $payment = $this->supplierService->recordPayment(
                supplier: $supplier,
                amount: $validated->integer('amount'),
                paymentDate: $validated->string('payment_date')->toString(),
                purchaseId: $validated->string('purchase_id')->toString() ?: null,
                notes: $validated->string('notes')->toString() !== '' ? $validated->string('notes')->toString() : null,
                userId: $request->user()?->id,
            );
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pembayaran hutang tercatat.',
        ]);

        ActivityLogger::log(
            ActivityAction::SupplierPaymentCreate,
            'Pembayaran hutang ke '.$supplier->nama.' (Rp '.number_format($payment->amount, 0, ',', '.').')',
            $payment->purchase ?? $supplier,
            ['amount' => $payment->amount],
        );

        return back();
    }
}
