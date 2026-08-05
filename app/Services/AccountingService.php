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
     * deduksi stok gudang asal + 2 entry cash flow (revenue & shipping).
     */
    public function recordOrderCompleted(Order $order, ?int $userId): void
    {
        DB::transaction(function () use ($order, $userId): void {
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->with('items.book')
                ->findOrFail((int) $order->getKey());

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

            $inventoryService = app(InventoryService::class);
            $inventoryService->deductForOrder($lockedOrder, $userId);

            $this->createEntry($lockedOrder, FlowType::Revenue, $subtotal, 'Pendapatan order '.$lockedOrder->no_order);
            $this->createEntry($lockedOrder, FlowType::Shipping, $shipping, 'Ongkir order '.$lockedOrder->no_order);
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
