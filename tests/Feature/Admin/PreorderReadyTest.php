<?php

use App\Enums\OrderStatus;
use App\Mail\PreorderReadyMail;
use App\Models\Book;
use App\Models\Order;
use App\Models\User;
use App\Notifications\PreorderReadyNotification;
use App\Services\PreorderReadyService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

it('emails and notifies customers when pre-order stock arrives', function (): void {
    Mail::fake();
    Notification::fake();

    $customer = User::factory()->create(['email' => 'pembeli@example.test']);
    $book = Book::factory()->create([
        'aktif' => true,
        'harga' => 50000,
        'is_preorder' => true,
    ]);

    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'status' => OrderStatus::MenungguKonfirmasi,
        'email_pembeli' => 'pembeli@example.test',
    ]);
    $order->items()->create([
        'book_id' => $book->id,
        'book_edition_id' => $book->activeEdition?->id,
        'is_preorder' => true,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 2,
        'price_original' => $book->harga,
        'price_final' => $book->harga,
    ]);

    app(PreorderReadyService::class)->handleStockArrival($book);

    Mail::assertQueued(PreorderReadyMail::class, function (PreorderReadyMail $mail) use ($customer, $order): bool {
        return $mail->hasTo($customer->email) && $mail->order->is($order);
    });

    Notification::assertSentTo($customer, PreorderReadyNotification::class);
});

it('skips orders whose status is no longer waiting', function (): void {
    Mail::fake();
    Notification::fake();

    $customer = User::factory()->create(['email' => 'pembeli@example.test']);
    $book = Book::factory()->create(['aktif' => true, 'harga' => 50000, 'is_preorder' => true]);

    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'status' => OrderStatus::Batal,
        'email_pembeli' => 'pembeli@example.test',
    ]);
    $order->items()->create([
        'book_id' => $book->id,
        'is_preorder' => true,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 1,
        'price_original' => $book->harga,
        'price_final' => $book->harga,
    ]);

    app(PreorderReadyService::class)->handleStockArrival($book);

    Mail::assertNothingQueued();
    Notification::assertNothingSent();
});
