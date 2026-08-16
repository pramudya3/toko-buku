<?php

namespace App\Services;

use App\Enums\FlowType;
use App\Models\CashFlow;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pencatatan arus kas (CF-01..06, BR-06).
 *
 * Entry bersifat read-only — hanya dibuat otomatis dari service ini,
 * tidak ada route tulis dari controller.
 */
final class AccountingService
{
    /**
     * Order selesai → 1 transaksi DB atomik (BR-06):
     * 2 entry cash flow (revenue & shipping).
     *
     * Catatan: deduksi stok sudah terjadi saat order diproses
     * (InventoryService::deductForOrder) — bukan lagi di sini.
     */
    public function recordOrderCompleted(Order $order, ?string $userId): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->with('items.book')
                ->findOrFail($order->getKey());

            $recordedTypes = $lockedOrder->cashFlows()
                ->whereIn('flow_type', [FlowType::Revenue->value, FlowType::Shipping->value])
                ->pluck('flow_type')
                ->unique();

            if ($recordedTypes->isNotEmpty()) {
                if ($recordedTypes->count() === 2) {
                    return;
                }

                throw new RuntimeException('Catatan arus kas order tidak lengkap dan perlu diperiksa.');
            }

            $subtotal = $lockedOrder->subtotal();
            $shipping = $lockedOrder->shipping_cost;

            $this->createEntry($lockedOrder, FlowType::Revenue, $subtotal, 'Pendapatan order '.$lockedOrder->no_order);
            $this->createEntry($lockedOrder, FlowType::Shipping, $shipping, 'Ongkir order '.$lockedOrder->no_order);
        });
    }

    /**
     * Order batal yang sudah lunas → 1 entry refund (outflow) otomatis.
     * Idempotent: entry refund hanya dibuat sekali per order.
     */
    public function recordOrderRefund(Order $order, ?string $userId = null): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->cashFlows()->where('flow_type', FlowType::Refund->value)->exists()) {
                return;
            }

            $amount = (int) $lockedOrder->total;

            if ($amount <= 0) {
                return;
            }

            $this->createEntry($lockedOrder, FlowType::Refund, $amount, 'Refund order '.$lockedOrder->no_order.' (dibatalkan)');
        });
    }

    private function createEntry(Order $order, FlowType $type, int $amount, string $description): void
    {
        if ($amount < 0) {
            throw new RuntimeException('Jumlah arus kas tidak boleh negatif.');
        }

        CashFlow::create([
            'order_id' => $order->id,
            'entry_date' => now()->toDateString(),
            'flow_type' => $type,
            'amount' => $amount,
            'description' => $description,
        ]);
    }
}
