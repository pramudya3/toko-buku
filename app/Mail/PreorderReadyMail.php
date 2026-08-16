<?php

namespace App\Mail;

use App\Models\Book;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email pemberitahuan stok pre-order telah tersedia.
 */
class PreorderReadyMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Order $order,
        public Book $book,
        public int $qty,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Stok {$this->book->judul} telah tersedia",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.preorder-ready',
        );
    }
}
