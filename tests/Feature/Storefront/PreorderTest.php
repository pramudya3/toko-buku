<?php

use App\Enums\OrderStatus;
use App\Models\Book;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\SalesChannel;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->customer = User::factory()->create();
    Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    Setting::set('origin_postal_code', '65144');
});

it('allows pre-order books to be ordered even when stock is empty', function (): void {
    $book = Book::factory()->create([
        'aktif' => true,
        'harga' => 50000,
        'is_preorder' => true,
        'preorder_eta' => now()->addMonth()->toDateString(),
    ]);

    $this->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 2])->assertRedirect();

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'nama_pembeli' => 'Pembeli PO',
            'whatsapp_pembeli' => '08123456789',
            'metode_bayar' => 'transfer',
            'metode_pengambilan' => 'ambil',
        ])
        ->assertRedirect();

    $order = Order::latest('id')->firstOrFail();
    $item = $order->items()->first();

    expect($order->status)->toBe(OrderStatus::MenungguKonfirmasi)
        ->and($item)->not->toBeNull()
        ->and($item->is_preorder)->toBeTrue();
});

it('still rejects out-of-stock books that are not pre-order', function (): void {
    $book = Book::factory()->create(['aktif' => true, 'harga' => 50000]);

    $this->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 1])
        ->assertSessionHasErrors('qty');
});

it('does not auto-process cash orders containing pre-order items', function (): void {
    PaymentMethod::firstOrCreate(['code' => 'cash'], ['name' => 'Cash', 'is_active' => true]);
    SalesChannel::firstOrCreate(['code' => 'toko'], ['name' => 'Toko', 'is_active' => true]);

    $book = Book::factory()->create(['aktif' => true, 'harga' => 50000, 'is_preorder' => true]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Pembeli Tunai',
            'metode_bayar' => 'cash',
            'sumber_pembelian' => 'toko',
            'metode_pengambilan' => 'ambil',
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ])
        ->assertRedirect();

    $order = Order::latest('id')->firstOrFail();

    expect($order->status)->toBe(OrderStatus::MenungguKonfirmasi)
        ->and($order->payment_status->value)->toBe('lunas')
        ->and($order->items()->first()->is_preorder)->toBeTrue();
});

it('filters the storefront catalog for pre-order and excludes them from empty', function (): void {
    Book::factory()->create(['aktif' => true, 'harga' => 40000, 'is_preorder' => true]);
    Book::factory()->create(['aktif' => true, 'harga' => 40000]);

    $this->get(route('books.catalog', ['stok' => 'preorder']))
        ->assertInertia(fn ($page) => $page
            ->where('filters.stok', 'preorder')
            ->has('books.data', 1)
            ->where('books.data.0.is_preorder', true));

    // Tab "Habis" hanya buku non-pre-order.
    $this->get(route('books.catalog', ['stok' => 'empty']))
        ->assertInertia(fn ($page) => $page
            ->has('books.data', 1)
            ->where('books.data.0.is_preorder', false));
});
