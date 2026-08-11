<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Book;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\User;
use App\Services\SalesXlsxExporter;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('shows the sales report page with summary and filters', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 40000]);
    $edition = $book->editions()->orderBy('cetakan_ke')->first();

    $order = Order::factory()->create([
        'nama_pembeli' => 'Budi',
        'metode_bayar' => PaymentMethod::Transfer->value,
        'status' => OrderStatus::Selesai->value,
        'shipping_cost' => 10000,
        'total' => 90000,
    ]);
    $order->items()->create([
        'book_id' => $book->id,
        'book_edition_id' => $edition->id,
        'judul_snapshot' => $book->judul,
        'edition_snapshot' => 'Cetakan ke-1',
        'qty' => 2,
        'harga_snapshot' => 40000,
        'harga_beli_snapshot' => $edition->harga_beli,
        'price_original' => 40000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 40000,
    ]);

    $resp = $this->actingAs($this->admin)->get(route('admin.sales-reports.index'));
    $resp->assertOk();
    $props = inertiaProps($resp);

    expect($props['summary']['order_count'])->toBe(1)
        ->and($props['summary']['omzet'])->toBe(80000)
        ->and($props['summary']['hpp'])->toBe($edition->harga_beli * 2)
        ->and($props['summary']['laba'])->toBe(80000 - ($edition->harga_beli * 2))
        ->and($props['summary']['shipping'])->toBe(10000)
        ->and($props['rows']['total'])->toBe(1)
        // Preview per item — kolom sama dengan export .xlsx
        ->and($props['rows']['data'][0]['no_order'])->toBe($order->no_order)
        ->and($props['rows']['data'][0]['buku'])->toBe($book->judul)
        ->and($props['rows']['data'][0]['cetakan'])->toBe('Cetakan ke-1')
        ->and($props['rows']['data'][0]['qty'])->toBe(2)
        ->and($props['rows']['data'][0]['harga_final'])->toBe(40000)
        ->and($props['rows']['data'][0]['hpp'])->toBe($edition->harga_beli)
        ->and($props['rows']['data'][0]['laba'])->toBe((40000 - $edition->harga_beli) * 2);
});

it('reduces omzet and profit by sales returns in the same period', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 40000]);
    $edition = $book->editions()->orderBy('cetakan_ke')->first();

    $order = Order::factory()->create([
        'nama_pembeli' => 'Budi',
        'metode_bayar' => PaymentMethod::Transfer->value,
        'status' => OrderStatus::Selesai->value,
        'shipping_cost' => 10000,
        'total' => 90000,
    ]);
    $orderItem = $order->items()->create([
        'book_id' => $book->id,
        'book_edition_id' => $edition->id,
        'judul_snapshot' => $book->judul,
        'edition_snapshot' => 'Cetakan ke-1',
        'qty' => 2,
        'harga_snapshot' => 40000,
        'harga_beli_snapshot' => $edition->harga_beli,
        'price_original' => 40000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 40000,
    ]);

    $return = SalesReturn::create([
        'order_id' => $order->id,
        'return_date' => now()->toDateString(),
        'total_refund' => 40000,
        'user_id' => $this->admin->id,
    ]);
    SalesReturnItem::create([
        'sales_return_id' => $return->id,
        'order_item_id' => $orderItem->id,
        'book_id' => $book->id,
        'book_edition_id' => $edition->id,
        'qty' => 1,
        'price_refund' => 40000,
        'condition' => 'baik',
    ]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.sales-reports.index'))
        ->assertOk());

    expect($props['summary']['omzet'])->toBe(40000)
        ->and($props['summary']['hpp'])->toBe($edition->harga_beli)
        ->and($props['summary']['item_count'])->toBe(0)
        ->and($props['summary']['laba'])->toBe(40000 - $edition->harga_beli)
        // 2 baris: penjualan + retur (qty & laba negatif).
        ->and($props['rows']['total'])->toBe(2)
        ->and($props['rows']['data'][1]['qty'])->toBe(-1)
        ->and($props['rows']['data'][1]['harga_final'])->toBe(40000)
        ->and($props['rows']['data'][1]['laba'])->toBe(-(40000 - $edition->harga_beli));
});

it('applies returns in the period they occurred', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 40000]);
    $edition = $book->editions()->orderBy('cetakan_ke')->first();

    // Penjualan bulan lalu — di luar periode laporan default (bulan ini).
    $order = Order::factory()->create([
        'metode_bayar' => PaymentMethod::Transfer->value,
        'status' => OrderStatus::Selesai->value,
        'created_at' => now()->subMonth(),
        'total' => 80000,
    ]);
    $orderItem = $order->items()->create([
        'book_id' => $book->id,
        'book_edition_id' => $edition->id,
        'judul_snapshot' => $book->judul,
        'edition_snapshot' => 'Cetakan ke-1',
        'qty' => 2,
        'harga_snapshot' => 40000,
        'harga_beli_snapshot' => $edition->harga_beli,
        'price_original' => 40000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 40000,
    ]);

    // Retur terjadi bulan ini → mengurangi omzet periode ini.
    $return = SalesReturn::create([
        'order_id' => $order->id,
        'return_date' => now()->toDateString(),
        'total_refund' => 40000,
        'user_id' => $this->admin->id,
    ]);
    SalesReturnItem::create([
        'sales_return_id' => $return->id,
        'order_item_id' => $orderItem->id,
        'book_id' => $book->id,
        'book_edition_id' => $edition->id,
        'qty' => 1,
        'price_refund' => 40000,
        'condition' => 'baik',
    ]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.sales-reports.index'))
        ->assertOk());

    expect($props['summary']['order_count'])->toBe(0)
        ->and($props['summary']['omzet'])->toBe(-40000)
        ->and($props['rows']['total'])->toBe(1)
        ->and($props['rows']['data'][0]['qty'])->toBe(-1);
});

it('includes return rows in the xlsx export rows', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 40000]);
    $edition = $book->editions()->orderBy('cetakan_ke')->first();

    $order = Order::factory()->create([
        'metode_bayar' => PaymentMethod::Transfer->value,
        'status' => OrderStatus::Selesai->value,
        'total' => 80000,
    ]);
    $orderItem = $order->items()->create([
        'book_id' => $book->id,
        'book_edition_id' => $edition->id,
        'judul_snapshot' => $book->judul,
        'edition_snapshot' => 'Cetakan ke-1',
        'qty' => 2,
        'harga_snapshot' => 40000,
        'harga_beli_snapshot' => $edition->harga_beli,
        'price_original' => 40000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 40000,
    ]);

    $return = SalesReturn::create([
        'order_id' => $order->id,
        'return_date' => now()->toDateString(),
        'total_refund' => 40000,
        'user_id' => $this->admin->id,
    ]);
    SalesReturnItem::create([
        'sales_return_id' => $return->id,
        'order_item_id' => $orderItem->id,
        'book_id' => $book->id,
        'book_edition_id' => $edition->id,
        'qty' => 1,
        'price_refund' => 40000,
        'condition' => 'baik',
    ]);

    $rows = app(SalesXlsxExporter::class)->buildRows(now()->startOfMonth(), now());

    expect($rows)->toHaveCount(2)
        ->and($rows->first()['qty'])->toBe(2)
        ->and($rows->last()['qty'])->toBe(-1)
        ->and($rows->last()['laba'])->toBe(-(40000 - $edition->harga_beli));
});

it('filters orders by payment method', function (): void {
    $transfer = Order::factory()->create(['metode_bayar' => PaymentMethod::Transfer->value, 'total' => 50000]);
    $cod = Order::factory()->create(['metode_bayar' => PaymentMethod::Cod->value, 'total' => 60000]);
    $book = Book::factory()->withStock(malang: 5)->create(['harga' => 60000]);
    $cod->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'qty' => 1,
        'harga_snapshot' => 60000,
        'harga_beli_snapshot' => 30000,
        'price_original' => 60000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 60000,
    ]);

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.sales-reports.index', [
        'metode_bayar' => PaymentMethod::Cod->value,
    ])));

    expect(collect($props['rows']['data'])->pluck('no_order')->all())->toBe([$cod->no_order])
        ->and($props['summary']['order_count'])->toBe(1);
});

it('exports sales report as xlsx with per-item HPP and profit rows', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 40000]);
    $edition = $book->editions()->orderBy('cetakan_ke')->first();

    $order = Order::factory()->create([
        'nama_pembeli' => 'Siti',
        'metode_bayar' => PaymentMethod::Transfer->value,
        'status' => OrderStatus::Selesai->value,
        'shipping_cost' => 10000,
        'total' => 90000,
    ]);
    $order->items()->create([
        'book_id' => $book->id,
        'book_edition_id' => $edition->id,
        'judul_snapshot' => $book->judul,
        'edition_snapshot' => 'Cetakan ke-1',
        'qty' => 2,
        'harga_snapshot' => 40000,
        'harga_beli_snapshot' => $edition->harga_beli,
        'price_original' => 40000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 40000,
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.sales-reports.export', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain('laporan-penjualan_')->toEndWith('.xlsx');

    // Baris laporan benar: 1 baris item dengan HPP & laba.
    $rows = app(SalesXlsxExporter::class)->buildRows(now()->startOfMonth(), now(), PaymentMethod::Transfer->value);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['no_order'])->toBe($order->no_order)
        ->and($rows[0]['buku'])->toBe($book->judul)
        ->and($rows[0]['cetakan'])->toBe('Cetakan ke-1')
        ->and($rows[0]['qty'])->toBe(2)
        ->and($rows[0]['harga_final'])->toBe(40000)
        ->and($rows[0]['hpp'])->toBe($edition->harga_beli)
        ->and($rows[0]['laba'])->toBe((40000 - $edition->harga_beli) * 2);
});

it('builds empty rows when no orders match the filters', function (): void {
    $rows = app(SalesXlsxExporter::class)->buildRows(
        now()->subDays(30),
        now()->subDays(29),
        PaymentMethod::Transfer->value,
    );

    expect($rows)->toBeEmpty();
});

it('normalizes inverted date range', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create(['harga' => 30000]);
    $order = Order::factory()->create(['total' => 30000]);
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'qty' => 1,
        'harga_snapshot' => 30000,
        'harga_beli_snapshot' => 20000,
        'price_original' => 30000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 30000,
    ]);

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.sales-reports.index', [
        'from' => now()->toDateString(),
        'to' => now()->subDays(5)->toDateString(), // dibalik
    ])));

    expect($props['summary']['order_count'])->toBe(1);
});
