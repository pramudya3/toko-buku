<?php

use App\Enums\OrderStatus;
use App\Models\Book;
use App\Models\Order;
use App\Models\StockRequest;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->customer = User::factory()->create();
});

it('shows only waiting orders containing pre-order items', function (): void {
    $preorderBook = Book::factory()->create(['aktif' => true, 'harga' => 50000, 'is_preorder' => true, 'preorder_eta' => now()->addMonth()->toDateString()]);
    $normalBook = Book::factory()->create(['aktif' => true, 'harga' => 40000, 'stok' => 5]);

    $waitingPo = Order::factory()->create([
        'user_id' => $this->customer->id,
        'status' => OrderStatus::MenungguKonfirmasi,
        'nama_pembeli' => 'Pembeli PO',
        'no_hp' => '0812310293123',
    ]);
    $waitingPo->items()->create([
        'book_id' => $preorderBook->id,
        'is_preorder' => true,
        'judul_snapshot' => $preorderBook->judul,
        'harga_snapshot' => $preorderBook->harga,
        'qty' => 2,
        'price_original' => $preorderBook->harga,
        'price_final' => $preorderBook->harga,
    ]);

    // Order menunggu tanpa item pre-order — tidak boleh tampil.
    $waitingNormal = Order::factory()->create([
        'user_id' => $this->customer->id,
        'status' => OrderStatus::MenungguKonfirmasi,
    ]);
    $waitingNormal->items()->create([
        'book_id' => $normalBook->id,
        'is_preorder' => false,
        'judul_snapshot' => $normalBook->judul,
        'harga_snapshot' => $normalBook->harga,
        'qty' => 1,
        'price_original' => $normalBook->harga,
        'price_final' => $normalBook->harga,
    ]);

    // Order pre-order yang sudah diproses — tidak boleh tampil.
    $processed = Order::factory()->create([
        'user_id' => $this->customer->id,
        'status' => OrderStatus::Diproses,
    ]);
    $processed->items()->create([
        'book_id' => $preorderBook->id,
        'is_preorder' => true,
        'judul_snapshot' => $preorderBook->judul,
        'harga_snapshot' => $preorderBook->harga,
        'qty' => 1,
        'price_original' => $preorderBook->harga,
        'price_final' => $preorderBook->harga,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.preorder'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders', 1)
            ->where('orders.0.no_order', $waitingPo->no_order)
            ->where('orders.0.total_qty', 2)
            ->where('orders.0.books.0', $preorderBook->judul)
            ->where('wa_template_ready', config('whatsapp.template_ready'))
            ->where('nama_lembaga', ''));
});

it('lists the pre-order waitlist and excludes it from stock requests menu', function (): void {
    $preorderBook = Book::factory()->create(['aktif' => true, 'harga' => 50000, 'is_preorder' => true]);
    $normalBook = Book::factory()->create(['aktif' => true, 'harga' => 40000]);

    StockRequest::create(['book_id' => $preorderBook->id, 'user_id' => $this->customer->id]);
    StockRequest::create(['book_id' => $normalBook->id, 'user_id' => $this->customer->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.preorder'))
        ->assertInertia(fn ($page) => $page
            ->has('waitlist', 1)
            ->where('waitlist.0.book.judul', $preorderBook->judul));

    // Menu Pengajuan Stok hanya buku non-pre-order.
    $this->actingAs($this->admin)
        ->get(route('admin.stock-requests.index'))
        ->assertInertia(fn ($page) => $page
            ->has('requests.data', 1)
            ->where('requests.data.0.book.judul', $normalBook->judul));
});

it('aggregates per-book totals for paid orders and waitlist', function (): void {
    $book = Book::factory()->create(['aktif' => true, 'harga' => 50000, 'is_preorder' => true, 'preorder_eta' => now()->addMonth()->toDateString()]);

    $orderA = Order::factory()->create(['user_id' => $this->customer->id, 'status' => OrderStatus::MenungguKonfirmasi]);
    $orderA->items()->create([
        'book_id' => $book->id,
        'is_preorder' => true,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 3,
        'price_original' => $book->harga,
        'price_final' => $book->harga,
    ]);

    $orderB = Order::factory()->create(['user_id' => $this->customer->id, 'status' => OrderStatus::MenungguKonfirmasi]);
    $orderB->items()->create([
        'book_id' => $book->id,
        'is_preorder' => true,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 1,
        'price_original' => $book->harga,
        'price_final' => $book->harga,
    ]);

    StockRequest::create(['book_id' => $book->id, 'user_id' => $this->customer->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.preorder'))
        ->assertInertia(fn ($page) => $page
            ->has('summary', 1)
            ->where('summary.0.judul', $book->judul)
            ->where('summary.0.qty_dipesan', 4)
            ->where('summary.0.jumlah_pembeli', 2)
            ->where('summary.0.jumlah_waitlist', 1));
});

it('shows the book ETA in the summary for waitlist-only books', function (): void {
    $book = Book::factory()->create([
        'aktif' => true,
        'harga' => 50000,
        'is_preorder' => true,
        'preorder_eta' => '2026-09-01',
    ]);

    StockRequest::create(['book_id' => $book->id, 'user_id' => $this->customer->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.preorder'))
        ->assertInertia(fn ($page) => $page
            ->has('summary', 1)
            ->where('summary.0.judul', $book->judul)
            ->where('summary.0.eta', '2026-09-01'));
});

it('blocks customers from the pre-order page', function (): void {
    $this->actingAs($this->customer)
        ->get(route('admin.orders.preorder'))
        ->assertForbidden();
});
