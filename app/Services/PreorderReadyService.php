<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Mail\PreorderReadyMail;
use App\Models\Book;
use App\Models\OrderItem;
use App\Models\StockRequest;
use App\Notifications\PreorderReadyNotification;
use App\Notifications\StockRequestReadyNotification;
use Illuminate\Support\Facades\Mail;

/**
 * Beri tahu customer saat stok buku pre-order telah tersedia (Barang Masuk).
 */
final class PreorderReadyService
{
    /**
     * Cari order menunggu yang berisi item pre-order buku ini, lalu kirim
     * email + notifikasi lonceng ke pemiliknya. Pengguna waitlist (pengajuan
     * stok, tanpa bayar) juga diberi tahu.
     */
    public function handleStockArrival(Book $book): void
    {
        $this->notifyPaidOrders($book);
        $this->notifyWaitlist($book);
    }

    /**
     * Order yang sudah dibayar / menunggu diproses.
     */
    private function notifyPaidOrders(Book $book): void
    {
        $pendingItems = OrderItem::query()
            ->where('book_id', $book->id)
            ->where('is_preorder', true)
            ->whereHas('order', fn ($query) => $query->where('status', OrderStatus::MenungguKonfirmasi))
            ->with('order.user:id,name,email')
            ->get();

        foreach ($pendingItems->groupBy('order_id') as $orderId => $items) {
            $order = $items->first()->order;
            $email = $order->email_pembeli ?? $order->user?->email;

            if ($email !== null) {
                Mail::to($email)->queue(new PreorderReadyMail(
                    $order,
                    $book,
                    (int) $items->sum('qty'),
                ));
            }

            if ($order->user_id !== null && $order->user !== null) {
                $order->user->notify(new PreorderReadyNotification($order, $book));
            }
        }
    }

    /**
     * Waitlist: pengajuan stok buku ini (tanpa membayar).
     */
    private function notifyWaitlist(Book $book): void
    {
        $waitlist = StockRequest::query()
            ->where('book_id', $book->id)
            ->with('user:id,name,email')
            ->get();

        foreach ($waitlist as $request) {
            $request->user->notify(new StockRequestReadyNotification($book));
        }
    }
}
