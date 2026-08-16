<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi perubahan status pesanan untuk customer (database channel).
 */
class OrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public OrderStatus $to) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $label = $this->to->label();

        return [
            'message' => "Pesanan {$this->order->no_order} telah {$label}.",
            'order_no' => $this->order->no_order,
            'status' => $this->to->value,
        ];
    }
}
