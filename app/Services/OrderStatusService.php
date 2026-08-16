<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusNotification;
use App\Support\ActivityLogger;
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
        OrderStatus::Dikirim->value => [OrderStatus::Selesai],
        OrderStatus::Selesai->value => [],
        OrderStatus::Batal->value => [],
    ];

    public function canTransition(Order $order, OrderStatus $to): bool
    {
        // Ambil sendiri (channel utama) & marketplace (pencatatan) boleh
        // langsung selesai dari diproses; channel utama mode kirim wajib
        // lewat dikirim (resi).
        if ($order->status === OrderStatus::Diproses
            && $to === OrderStatus::Selesai
            && (! $order->isMainChannel() || ($order->metode_pengambilan ?? 'kirim') === 'ambil')) {
            return true;
        }

        return in_array($to, self::TRANSITIONS[$order->status->value], true);
    }

    /**
     * Terapkan status pengiriman Biteship ke status order aplikasi
     * (satu-satunya sumber mapping — dipakai webhook & tombol refresh admin).
     *
     * @return bool true bila transisi terjadi
     */
    public function applyBiteshipStatus(Order $order, string $biteshipStatus, ?string $userId = null): bool
    {
        $mapped = match ($biteshipStatus) {
            'picked', 'in_transit', 'dropping_off' => OrderStatus::Dikirim,
            'delivered' => OrderStatus::Selesai,
            default => null,
        };

        if ($mapped === null || ! $this->canTransition($order, $mapped)) {
            return false;
        }

        $this->transition($order, $mapped, $userId);

        return true;
    }

    /**
     * Lakukan transisi status + side-effect sistemik (bila valid).
     */
    public function transition(Order $order, OrderStatus $to, ?string $userId = null): Order
    {
        return DB::transaction(function () use ($order, $to, $userId): Order {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! $this->canTransition($lockedOrder, $to)) {
                throw new RuntimeException(
                    "Transisi status tidak valid: {$lockedOrder->status->value} → {$to->value}.",
                );
            }

            $oldStatus = $lockedOrder->status;

            if ($to === OrderStatus::Selesai) {
                app(AccountingService::class)->recordOrderCompleted($lockedOrder, $userId);
            }

            // Stok sudah di-reserve saat diproses — batal dari diproses mengembalikannya.
            if ($to === OrderStatus::Batal && $lockedOrder->status === OrderStatus::Diproses) {
                app(InventoryService::class)->restoreForOrder($lockedOrder, $userId);
            }

            $lockedOrder->update(['status' => $to]);

            // Notifikasi ke customer pemilik pesanan (lonceng storefront).
            if ($lockedOrder->user_id !== null && $lockedOrder->user !== null) {
                $lockedOrder->user->notify(new OrderStatusNotification($lockedOrder, $to));
            }

            ActivityLogger::log(
                ActivityAction::OrderStatus,
                "Pesanan {$lockedOrder->no_order}: {$oldStatus->label()} → {$to->label()}",
                $lockedOrder,
                ['from' => $oldStatus->value, 'to' => $to->value],
                user: $userId !== null ? User::find($userId) : null,
            );

            return $lockedOrder->fresh();
        });
    }
}
