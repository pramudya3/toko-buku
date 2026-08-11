<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Book;
use App\Models\CashFlow;
use App\Models\Order;
use App\Models\SalesReturn;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->service = app(InventoryService::class);
    $this->malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang']);
    $this->defect = Warehouse::firstOrCreate(['kode' => 'defect'], ['nama' => 'Defect', 'is_defect' => true]);
});

/**
 * Order selesai dengan 1 item (cetakan ke-1), stok 5 di Malang.
 */
function makeReturnableOrder(User $admin, InventoryService $service, int $qty = 2): array
{
    $book = Book::factory()->withStock(malang: 5)->create(['harga' => 40000]);
    $edition = $book->editions()->orderBy('cetakan_ke')->first();

    $order = Order::factory()->create([
        'metode_bayar' => PaymentMethod::Transfer->value,
        'status' => OrderStatus::Selesai->value,
        'warehouse_origin' => 'malang',
        'total' => 80000,
    ]);

    $order->items()->create([
        'book_id' => $book->id,
        'book_edition_id' => $edition->id,
        'judul_snapshot' => $book->judul,
        'edition_snapshot' => 'Cetakan ke-1',
        'qty' => $qty,
        'harga_snapshot' => 40000,
        'harga_beli_snapshot' => $edition->harga_beli,
        'price_original' => 40000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 40000,
    ]);

    return ['book' => $book, 'edition' => $edition, 'order' => $order];
}

it('records a sales return: stock back to warehouse + refund cash flow', function (): void {
    ['book' => $book, 'edition' => $edition, 'order' => $order] = makeReturnableOrder($this->admin, $this->service);

    $this->actingAs($this->admin)
        ->post(route('admin.sales-returns.store'), [
            'order_id' => $order->id,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'order_item_id' => $order->items()->first()->id,
                    'qty' => 2,
                    'condition' => 'baik',
                    'reason' => 'Salah ukuran',
                ],
            ],
        ])
        ->assertRedirect();

    $salesReturn = SalesReturn::first();

    expect($salesReturn)->not->toBeNull()
        ->and($salesReturn->total_refund)->toBe(80000)
        ->and($salesReturn->items()->count())->toBe(1);

    // Stok kembali ke gudang asal (Malang): 5 - 0 (belum pernah deduct) + 2 = 7.
    expect($book->fresh()->stok)->toBe(7)
        ->and($edition->stocks()->where('warehouse_id', $this->malang->id)->sum('qty'))->toBe(7);

    // Refund tercatat di arus kas.
    $flow = CashFlow::where('order_id', $order->id)->where('flow_type', 'refund')->first();

    expect($flow)->not->toBeNull()
        ->and($flow->amount)->toBe(80000);
});

it('moves damaged returned items to the defect warehouse', function (): void {
    ['book' => $book, 'edition' => $edition, 'order' => $order] = makeReturnableOrder($this->admin, $this->service);

    $this->actingAs($this->admin)
        ->post(route('admin.sales-returns.store'), [
            'order_id' => $order->id,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'order_item_id' => $order->items()->first()->id,
                    'qty' => 1,
                    'condition' => 'rusak',
                    'reason' => 'Halaman sobek',
                ],
            ],
        ])
        ->assertRedirect();

    expect($edition->stocks()->where('warehouse_id', $this->malang->id)->sum('qty'))->toBe(5)
        ->and($edition->stocks()->where('warehouse_id', $this->defect->id)->sum('qty'))->toBe(1);
});

it('rejects returning more than the ordered quantity', function (): void {
    ['book' => $book, 'order' => $order] = makeReturnableOrder($this->admin, $this->service, qty: 2);

    $this->actingAs($this->admin)
        ->post(route('admin.sales-returns.store'), [
            'order_id' => $order->id,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'order_item_id' => $order->items()->first()->id,
                    'qty' => 5,
                    'condition' => 'baik',
                ],
            ],
        ])
        ->assertSessionHasErrors('items');

    expect(SalesReturn::count())->toBe(0);
});

it('rejects duplicate order items in a single return request', function (): void {
    ['order' => $order] = makeReturnableOrder($this->admin, $this->service, qty: 3);

    $itemId = $order->items()->first()->id;

    // Dua baris dengan order_item yang sama dalam 1 request — total 4 > sisa 3.
    $this->actingAs($this->admin)
        ->post(route('admin.sales-returns.store'), [
            'order_id' => $order->id,
            'return_date' => now()->toDateString(),
            'items' => [
                ['order_item_id' => $itemId, 'qty' => 2, 'condition' => 'baik'],
                ['order_item_id' => $itemId, 'qty' => 2, 'condition' => 'baik'],
            ],
        ])
        ->assertSessionHasErrors('items.0.order_item_id');

    expect(SalesReturn::count())->toBe(0);
});

it('rejects returns for cancelled orders', function (): void {
    ['order' => $order] = makeReturnableOrder($this->admin, $this->service);
    $order->update(['status' => OrderStatus::Batal->value]);

    $this->actingAs($this->admin)
        ->post(route('admin.sales-returns.store'), [
            'order_id' => $order->id,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'order_item_id' => $order->items()->first()->id,
                    'qty' => 1,
                    'condition' => 'baik',
                ],
            ],
        ])
        ->assertSessionHasErrors('order_id');

    expect(SalesReturn::count())->toBe(0);
});

it('only allows returns for completed orders', function (): void {
    ['order' => $order] = makeReturnableOrder($this->admin, $this->service);
    $order->update(['status' => OrderStatus::Diproses->value]);

    $this->actingAs($this->admin)
        ->post(route('admin.sales-returns.store'), [
            'order_id' => $order->id,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'order_item_id' => $order->items()->first()->id,
                    'qty' => 1,
                    'condition' => 'baik',
                ],
            ],
        ])
        ->assertSessionHasErrors('order_id');

    expect(SalesReturn::count())->toBe(0);
});

it('stores return notes', function (): void {
    ['order' => $order] = makeReturnableOrder($this->admin, $this->service);

    $this->actingAs($this->admin)
        ->post(route('admin.sales-returns.store'), [
            'order_id' => $order->id,
            'return_date' => now()->toDateString(),
            'notes' => 'Pembeli menukar dengan ukuran lain',
            'items' => [
                [
                    'order_item_id' => $order->items()->first()->id,
                    'qty' => 1,
                    'condition' => 'baik',
                ],
            ],
        ])
        ->assertRedirect();

    expect(SalesReturn::first()->notes)->toBe('Pembeli menukar dengan ukuran lain');
});

it('does not refund shipping cost', function (): void {
    ['order' => $order] = makeReturnableOrder($this->admin, $this->service);
    $order->update(['shipping_cost' => 15000]);

    $this->actingAs($this->admin)
        ->post(route('admin.sales-returns.store'), [
            'order_id' => $order->id,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'order_item_id' => $order->items()->first()->id,
                    'qty' => 2,
                    'condition' => 'baik',
                ],
            ],
        ])
        ->assertRedirect();

    // Refund hanya nilai barang (2 × 40000), ongkir tidak ikut diretur.
    $flow = CashFlow::where('order_id', $order->id)->where('flow_type', 'refund')->first();

    expect($flow->amount)->toBe(80000);
});

it('hides fully returned orders from order options', function (): void {
    ['order' => $order] = makeReturnableOrder($this->admin, $this->service, qty: 1);

    // Retur penuh (qty 1 = seluruh item).
    $this->actingAs($this->admin)
        ->post(route('admin.sales-returns.store'), [
            'order_id' => $order->id,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'order_item_id' => $order->items()->first()->id,
                    'qty' => 1,
                    'condition' => 'baik',
                ],
            ],
        ]);

    $this->actingAs($this->admin)
        ->getJson(route('admin.sales-returns.options.orders'))
        ->assertOk()
        ->assertExactJson([]);
});

it('rejects order detail for non-completed orders', function (): void {
    ['order' => $order] = makeReturnableOrder($this->admin, $this->service);
    $order->update(['status' => OrderStatus::Dikirim->value]);

    $this->actingAs($this->admin)
        ->getJson(route('admin.sales-returns.orders.detail', $order))
        ->assertStatus(422);
});

it('prevents double returns exceeding remaining qty', function (): void {
    ['book' => $book, 'order' => $order] = makeReturnableOrder($this->admin, $this->service, qty: 3);

    $itemId = $order->items()->first()->id;

    // Retur pertama: 2 unit.
    $this->actingAs($this->admin)
        ->post(route('admin.sales-returns.store'), [
            'order_id' => $order->id,
            'return_date' => now()->toDateString(),
            'items' => [
                ['order_item_id' => $itemId, 'qty' => 2, 'condition' => 'baik'],
            ],
        ])
        ->assertRedirect();

    // Retur kedua: 2 unit lagi — melebihi sisa (1).
    $this->actingAs($this->admin)
        ->post(route('admin.sales-returns.store'), [
            'order_id' => $order->id,
            'return_date' => now()->toDateString(),
            'items' => [
                ['order_item_id' => $itemId, 'qty' => 2, 'condition' => 'baik'],
            ],
        ])
        ->assertSessionHasErrors('items');

    expect(SalesReturn::count())->toBe(1);
});

it('shows order detail with returnable quantity', function (): void {
    ['book' => $book, 'order' => $order] = makeReturnableOrder($this->admin, $this->service, qty: 3);

    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.sales-returns.orders.detail', $order));

    $response->assertOk();

    expect($response->json('items.0.judul'))->toBe($book->judul)
        ->and($response->json('items.0.returnable_qty'))->toBe(3)
        ->and($response->json('items.0.price_refund'))->toBe(40000);
});

it('lists sales returns', function (): void {
    ['order' => $order] = makeReturnableOrder($this->admin, $this->service);

    $this->actingAs($this->admin)
        ->post(route('admin.sales-returns.store'), [
            'order_id' => $order->id,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'order_item_id' => $order->items()->first()->id,
                    'qty' => 1,
                    'condition' => 'baik',
                ],
            ],
        ]);

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.sales-returns.index')));

    expect($props['returns']['total'])->toBe(1)
        ->and($props['returns']['data'][0]['order']['no_order'])->toBe($order->no_order)
        ->and($props['returns']['data'][0]['total_refund'])->toBe(40000);
});
