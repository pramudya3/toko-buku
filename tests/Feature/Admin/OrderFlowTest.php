<?php

use App\Enums\OrderStatus;
use App\Enums\Warehouse;
use App\Models\Book;
use App\Models\CashFlow;
use App\Models\Order;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('creates a manual order and calculates prices via PricingService (ORD-03, ORD-08)', function (): void {
    $book = Book::factory()->withStock(malang: 20)->create(['harga' => 50000]);
    $customer = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'user_id' => $customer->id,
            'nama_pembeli' => $customer->name,
            'metode_bayar' => 'transfer',
            'items' => [
                ['book_id' => $book->id, 'qty' => 2],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $order = Order::latest('id')->first();

    expect($order->status)->toBe(OrderStatus::MenungguKonfirmasi)
        ->and($order->total)->toBe(100000)
        ->and($order->items()->first()->price_final)->toBe(50000);
});

it('generates a unique order number', function (): void {
    $book = Book::factory()->withStock()->create(['harga' => 10000]);

    foreach (range(1, 3) as $index) {
        $this->actingAs($this->admin)
            ->post(route('admin.orders.store'), [
                'nama_pembeli' => 'Pembeli '.$index,
                'metode_bayar' => 'transfer',
                'items' => [['book_id' => $book->id, 'qty' => 1]],
            ]);
    }

    $numbers = Order::pluck('no_order');

    expect($numbers->unique()->count())->toBe(3);
});

it('creates dropship order with end-customer data (DROP-01)', function (): void {
    $book = Book::factory()->withStock()->create(['harga' => 10000]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Reseller',
            'metode_bayar' => 'transfer',
            'is_dropship' => true,
            'end_customer_name' => 'Andini',
            'end_customer_whatsapp' => '081234567890',
            'end_customer_address' => 'Jl. Merdeka 45, Bandung',
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ])
        ->assertRedirect();

    $order = Order::latest('id')->first();

    expect($order->is_dropship)->toBeTrue()
        ->and($order->dropshipper->end_customer_name)->toBe('Andini');
});

it('validates order items', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'X',
            'metode_bayar' => 'transfer',
            'items' => [],
        ])
        ->assertSessionHasErrors(['items']);
});

it('processes an order: fills shipping cost, courier and warehouse origin (ORD-05)', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 50000]);
    $order = Order::factory()->create();
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 2,
        'price_original' => 50000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 50000,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.process', $order), [
            'shipping_cost' => 15000,
            'ekspedisi' => 'jne',
            'warehouse_origin' => Warehouse::Malang->value,
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Diproses)
        ->and($order->shipping_cost)->toBe(15000)
        ->and($order->warehouse_origin)->toBe(Warehouse::Malang)
        ->and($order->total)->toBe(115000);
});

it('rejects processing an order from an invalid state', function (): void {
    $order = Order::factory()->status(OrderStatus::Selesai)->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.process', $order), [
            'shipping_cost' => 10000,
            'ekspedisi' => 'jne',
            'warehouse_origin' => Warehouse::Malang->value,
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Selesai)
        ->and($order->shipping_cost)->toBe(0)
        ->and($order->warehouse_origin)->toBeNull()
        ->and($order->total)->toBe(0);
});

it('does not allow the generic status endpoint to bypass order processing', function (): void {
    $order = Order::factory()->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), [
            'status' => OrderStatus::Diproses->value,
        ])
        ->assertSessionHasErrors('status');

    expect($order->fresh()->status)->toBe(OrderStatus::MenungguKonfirmasi);
});

it('completes an order: deducts stock and creates 2 cash flows atomically (ORD-06, BR-06, CF-03)', function (): void {
    $book = Book::factory()->withStock(malang: 10, sidoarjo: 0)->create(['harga' => 50000]);
    $order = Order::factory()
        ->processed(shippingCost: '15000', warehouse: Warehouse::Malang)
        ->status(OrderStatus::Dikirim)
        ->create();

    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 2,
        'price_original' => 50000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 50000,
    ]);
    $order->update(['total' => 115000]);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Selesai->value])
        ->assertRedirect();

    expect($book->fresh()->stok)->toBe(8)
        ->and($book->fresh()->inventoryStock->stock_malang)->toBe(8)
        ->and(CashFlow::where('order_id', $order->id)->where('flow_type', 'revenue')->exists())->toBeTrue()
        ->and(CashFlow::where('order_id', $order->id)->where('flow_type', 'shipping')->exists())->toBeTrue()
        ->and(CashFlow::where('order_id', $order->id)->sum('amount'))->toBe(115000);
});

it('is idempotent: completing twice does not double deduct or duplicate entries (ORD-07)', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 50000]);
    $order = Order::factory()
        ->processed(shippingCost: '10000', warehouse: Warehouse::Malang)
        ->status(OrderStatus::Dikirim)
        ->create();

    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 1,
        'price_original' => 50000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 50000,
    ]);
    $order->update(['total' => 60000]);

    // Transisi pertama sukses.
    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Selesai->value])
        ->assertRedirect();

    // Transisi kedua (selesai → batal) ditolak guard.
    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Batal->value])
        ->assertRedirect();

    expect($book->fresh()->stok)->toBe(9)
        ->and(CashFlow::where('order_id', $order->id)->count())->toBe(2);
});

it('does not duplicate completion side effects from a stale order model', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 50000]);
    $order = Order::factory()
        ->processed(shippingCost: '10000', warehouse: Warehouse::Malang)
        ->status(OrderStatus::Dikirim)
        ->create();

    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 1,
        'price_original' => 50000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 50000,
    ]);
    $order->update(['total' => 60000]);

    $staleOrder = $order->fresh();
    app(\App\Services\OrderStatusService::class)->transition($staleOrder, OrderStatus::Selesai, $this->admin->id);

    expect(fn () => app(\App\Services\OrderStatusService::class)
        ->transition($staleOrder, OrderStatus::Selesai, $this->admin->id))
        ->toThrow(RuntimeException::class);

    expect($book->fresh()->stok)->toBe(9)
        ->and(CashFlow::where('order_id', $order->id)->count())->toBe(2);
});

it('cancels an order without side effects (CF-04)', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 50000]);
    $order = Order::factory()->create();
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 1,
        'price_original' => 50000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 50000,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Batal->value])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Batal)
        ->and($book->fresh()->stok)->toBe(10)
        ->and(CashFlow::where('order_id', $order->id)->count())->toBe(0);
});

it('shows order detail with items and pricing breakdown (ORD-02)', function (): void {
    $book = Book::factory()->withStock()->create(['harga' => 50000]);
    $order = Order::factory()->create();
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 2,
        'price_original' => 50000,
        'promo_discount_amount' => 5000,
        'tier_discount_amount' => 1000,
        'price_final' => 44000,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.show', $order))
        ->assertSuccessful()
        ->assertSee($order->no_order)
        ->assertSee($book->judul);
});
