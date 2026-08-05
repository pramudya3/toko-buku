<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Guard transisi status order (BR-05, ORD-04, ORD-07).
 *
 * Selesai/batal bersifat terminal & idempotent — side-effect (stok + cash flow)
 * hanya dipicu sekali saat transisi ke `selesai`.
 */
final class OrderStatusService
{
    /**
     * @var array<string, list<OrderStatus>>
     */
    public const TRANSITIONS = [
        OrderStatus::MenungguKonfirmasi->value => [OrderStatus::Diproses, OrderStatus::Batal],
        OrderStatus::Diproses->value => [OrderStatus::Dikirim, OrderStatus::Batal],
        OrderStatus::Dikirim->value => [OrderStatus::Selesai, OrderStatus::Batal],
        OrderStatus::Selesai->value => [],
        OrderStatus::Batal->value => [],
    ];

    public function canTransition(Order $order, OrderStatus $to): bool
    {
        return in_array($to, self::TRANSITIONS[$order->status->value], true);
    }

    /**
     * Lakukan transisi status + side-effect sistemik (bila valid).
     */
    public function transition(Order $order, OrderStatus $to, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($order, $to, $userId): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail((int) $order->getKey());

            if (! $this->canTransition($lockedOrder, $to)) {
                throw new RuntimeException(
                    "Transisi status tidak valid: {$lockedOrder->status->value} → {$to->value}.",
                );
            }

            if ($to === OrderStatus::Selesai) {
                app(AccountingService::class)->recordOrderCompleted($lockedOrder, $userId);
            }

            $lockedOrder->update(['status' => $to]);

            return $lockedOrder->fresh();
        });
    }
}
