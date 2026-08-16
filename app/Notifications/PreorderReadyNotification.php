<?php

namespace App\Notifications;

use App\Models\Book;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi (lonceng) stok pre-order telah tersedia.
 */
class PreorderReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public Book $book) {}

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
        return [
            'message' => "Stok {$this->book->judul} telah tersedia — pesanan {$this->order->no_order} siap diproses.",
            'order_no' => $this->order->no_order,
            'status' => 'preorder-ready',
        ];
    }
}
