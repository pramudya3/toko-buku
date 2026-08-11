<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Book;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Supplier;
use App\Models\SupplierPurchase;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('renders the printable sales order invoice', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create(['harga' => 40000]);

    $order = Order::factory()->create([
        'nama_pembeli' => 'Budi',
        'metode_bayar' => PaymentMethod::Transfer->value,
        'status' => OrderStatus::Selesai->value,
        'shipping_cost' => 10000,
        'total' => 90000,
    ]);
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'edition_snapshot' => 'Cetakan ke-1',
        'qty' => 2,
        'harga_snapshot' => 40000,
        'price_original' => 40000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 40000,
    ]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.orders.invoice', $order))
        ->assertOk());

    expect($props['order']['no_order'])->toBe($order->no_order)
        ->and($props['order']['items'])->toHaveCount(1)
        ->and($props['order']['items'][0]['price_final'])->toBe(40000)
        ->and($props['store']['nama'])->toBeString();
});

it('renders the printable supplier purchase note', function (): void {
    $supplier = Supplier::create(['nama' => 'PT Penerbit Sejahtera']);
    $book = Book::factory()->withStock(malang: 5)->create();

    $purchase = SupplierPurchase::create([
        'supplier_id' => $supplier->id,
        'ref_code' => 'INV-2026-001',
        'purchase_date' => now()->toDateString(),
        'total' => 60000,
        'user_id' => $this->admin->id,
    ]);
    $purchase->items()->create([
        'book_id' => $book->id,
        'qty' => 2,
        'price' => 30000,
        'subtotal' => 60000,
    ]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.purchases.invoice', $purchase))
        ->assertOk());

    expect($props['purchase']['ref_code'])->toBe('INV-2026-001')
        ->and($props['purchase']['supplier']['nama'])->toBe('PT Penerbit Sejahtera')
        ->and($props['purchase']['items'])->toHaveCount(1);
});

it('renders the printable sales return note', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create(['harga' => 40000]);

    $order = Order::factory()->create([
        'nama_pembeli' => 'Budi',
        'metode_bayar' => PaymentMethod::Cash->value,
        'status' => OrderStatus::Selesai->value,
        'total' => 80000,
    ]);
    $orderItem = $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'edition_snapshot' => 'Cetakan ke-1',
        'qty' => 2,
        'harga_snapshot' => 40000,
        'price_original' => 40000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 40000,
    ]);

    $return = SalesReturn::create([
        'order_id' => $order->id,
        'return_date' => now()->toDateString(),
        'total_refund' => 40000,
        'notes' => 'Salah ukuran',
        'user_id' => $this->admin->id,
    ]);
    SalesReturnItem::create([
        'sales_return_id' => $return->id,
        'order_item_id' => $orderItem->id,
        'book_id' => $book->id,
        'qty' => 1,
        'price_refund' => 40000,
        'condition' => 'baik',
        'reason' => 'Salah ukuran',
    ]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.sales-returns.invoice', $return))
        ->assertOk());

    expect($props['retur']['total_refund'])->toBe(40000)
        ->and($props['retur']['order']['no_order'])->toBe($order->no_order)
        ->and($props['retur']['items'])->toHaveCount(1)
        ->and($props['retur']['items'][0]['order_item']['judul_snapshot'])->toBe($book->judul);
});
