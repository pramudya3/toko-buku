<?php

namespace App\Notifications;

use App\Models\Book;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi (lonceng + email) stok buku pre-order tersedia bagi yang
 * menunggu tanpa membayar (waitlist / pengajuan stok).
 */
class StockRequestReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Book $book) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => "Stok {$this->book->judul} telah tersedia — pesanan Anda sekarang bisa dibuat.",
            'book_id' => $this->book->id,
            'status' => 'preorder-ready',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Stok {$this->book->judul} telah tersedia")
            ->greeting('Halo!')
            ->line("Buku {$this->book->judul} yang Anda tunggu telah tersedia.")
            ->line('Anda sekarang bisa memesan buku tersebut di toko kami.')
            ->action('Lihat Buku', url('/buku/'.$this->book->id))
            ->line('Terima kasih telah menunggu.');
    }
}
