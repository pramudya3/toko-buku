<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Book;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

function recapOrder(User $admin, string $metode, int $total, int $qty = 1, ?string $sumber = null): Order
{
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => $total]);

    $order = Order::factory()->create([
        'metode_bayar' => $metode,
        'status' => OrderStatus::Selesai->value,
        'shipping_cost' => 10000,
        'total' => $total + 10000,
        'sumber_pembelian' => $sumber,
    ]);

    $order->items()->create([
        'book_id' => $book->id,
        'book_edition_id' => $book->editions()->orderBy('cetakan_ke')->first()->id,
        'judul_snapshot' => $book->judul,
        'qty' => $qty,
        'harga_snapshot' => $total,
        'harga_beli_snapshot' => 30000,
        'price_original' => $total,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => $total,
    ]);

    return $order;
}

it('groups sales by day with omzet, payment split, hpp and profit', function (): void {
    recapOrder($this->admin, PaymentMethod::Cash->value, 100000);
    recapOrder($this->admin, PaymentMethod::Transfer->value, 50000, qty: 2);
    recapOrder($this->admin, PaymentMethod::Cod->value, 75000);

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.daily-recap.index', [
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->toDateString(),
    ])));

    $row = $props['rows'][0];

    expect($row['order_count'])->toBe(3)
        // omzet = total order tanpa ongkir (total - shipping_cost)
        ->and($row['omzet'])->toBe(225000)
        ->and($row['cash'])->toBe(100000)
        ->and($row['transfer'])->toBe(50000)
        ->and($row['cod'])->toBe(75000)
        // item: 1 + 2 + 1 = 4; HPP = 30000 × 4
        ->and($row['item_count'])->toBe(4)
        ->and($row['hpp'])->toBe(120000)
        // laba = (price_final - hpp) × qty
        ->and($row['laba'])->toBe((100000 - 30000) + (50000 - 30000) * 2 + (75000 - 30000));

    expect($props['totals']['order_count'])->toBe(3)
        ->and($props['totals']['laba'])->toBe($row['laba']);
});

it('filters the recap by sales channel', function (): void {
    recapOrder($this->admin, PaymentMethod::Cash->value, 100000, sumber: 'toko');
    recapOrder($this->admin, PaymentMethod::Transfer->value, 50000, qty: 2, sumber: 'shopee');
    recapOrder($this->admin, PaymentMethod::Cod->value, 75000, sumber: 'toko');

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.daily-recap.index', [
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->toDateString(),
        'sumber_pembelian' => 'toko',
    ])));

    $row = $props['rows'][0];

    expect($row['order_count'])->toBe(2)
        ->and($row['omzet'])->toBe(175000) // 100000 + 75000 (tanpa ongkir)
        ->and($row['cash'])->toBe(100000)
        ->and($row['cod'])->toBe(75000)
        ->and($row['transfer'])->toBe(0)
        // item: 1 + 1 = 2; HPP = 30000 × 2
        ->and($row['item_count'])->toBe(2)
        ->and($row['hpp'])->toBe(60000)
        ->and($props['filters']['sumber_pembelian'])->toBe('toko');
});

it('subtracts sales returns from the recap', function (): void {
    $order = recapOrder($this->admin, PaymentMethod::Cash->value, 100000);

    // Retur penuh 1 unit — refund = harga barang (ongkir tidak diretur).
    $item = $order->items()->first();
    $return = SalesReturn::create([
        'order_id' => $order->id,
        'return_date' => now()->toDateString(),
        'total_refund' => 100000,
        'user_id' => $this->admin->id,
    ]);
    SalesReturnItem::create([
        'sales_return_id' => $return->id,
        'order_item_id' => $item->id,
        'book_id' => $item->book_id,
        'book_edition_id' => $item->book_edition_id,
        'qty' => 1,
        'price_refund' => 100000,
        'condition' => 'baik',
    ]);

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.daily-recap.index', [
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->toDateString(),
    ])));

    $row = $props['rows'][0];

    expect($row['order_count'])->toBe(1)
        // omzet 100000 (tanpa ongkir) - refund 100000
        ->and($row['omzet'])->toBe(0)
        ->and($row['cash'])->toBe(0)
        ->and($row['transfer'])->toBe(0)
        ->and($row['cod'])->toBe(0)
        // item & HPP & laba bersih nol (retur penuh)
        ->and($row['item_count'])->toBe(0)
        ->and($row['hpp'])->toBe(0)
        ->and($row['laba'])->toBe(0);
});

it('excludes cancelled orders from the recap', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create(['harga' => 50000]);
    $order = Order::factory()->create([
        'metode_bayar' => PaymentMethod::Transfer->value,
        'status' => OrderStatus::Batal->value,
        'total' => 50000,
    ]);
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'qty' => 1,
        'harga_snapshot' => 50000,
        'harga_beli_snapshot' => 20000,
        'price_original' => 50000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 50000,
    ]);

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.daily-recap.index', [
        'from' => now()->startOfMonth()->toDateString(),
        'to' => now()->toDateString(),
    ])));

    expect($props['rows'])->toBeEmpty();
});

it('exports daily recap as xlsx', function (): void {
    recapOrder($this->admin, PaymentMethod::Cash->value, 100000);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.daily-recap.export', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    expect($response->headers->get('content-disposition'))
        ->toContain('rekap-harian_')
        ->toEndWith('.xlsx');
});
