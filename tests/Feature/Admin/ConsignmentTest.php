<?php

use App\Enums\CustomerTier;
use App\Models\Book;
use App\Models\ConsignmentDelivery;
use App\Models\ConsignmentReturn;
use App\Models\ConsignmentSale;
use App\Models\InventoryMovement;
use App\Models\Receivable;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->partner = User::factory()->customer()->create([
        'status_pelanggan' => CustomerTier::Bazaf,
    ]);
    $this->customerBiasa = User::factory()->customer()->create([
        'status_pelanggan' => CustomerTier::Reguler,
    ]);
    $this->service = app(InventoryService::class);
    Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang']);
});

/**
 * Buku dengan stok awal di gudang Malang.
 */
function makeStock(int $stok = 10, int $harga = 40000): Book
{
    return Book::factory()->withStock(malang: $stok)->create(['harga' => $harga]);
}

/**
 * Catat serah terima konsinyasi via endpoint.
 *
 * @param  array<int, array{book_id: string, qty: int}>  $items
 */
function actingDeliver(User $admin, string $partnerId, array $items): void
{
    test()->actingAs($admin)->post(route('admin.konsinyasi.deliveries.store'), [
        'customer_id' => $partnerId,
        'warehouse_id' => Warehouse::default()->id,
        'delivery_date' => '2026-08-20',
        'items' => $items,
    ])->assertRedirect();
}

/**
 * Catat laporan laku konsinyasi via endpoint.
 *
 * @param  array<int, array{book_id: string, qty: int, price: int}>  $items
 */
function actingSale(User $admin, string $partnerId, array $items): void
{
    test()->actingAs($admin)->post(route('admin.konsinyasi.sales.store'), [
        'customer_id' => $partnerId,
        'sale_date' => '2026-08-22',
        'items' => $items,
    ])->assertRedirect();
}

/**
 * Catat retur sisa konsinyasi via endpoint.
 */
function actingReturn(User $admin, string $partnerId, string $bookId, int $qty): void
{
    test()->actingAs($admin)->post(route('admin.konsinyasi.returns.store'), [
        'customer_id' => $partnerId,
        'book_id' => $bookId,
        'qty' => $qty,
        'return_date' => '2026-08-23',
    ])->assertRedirect();
}

it('shows konsinyasi page with bazaf partners only', function (): void {
    $response = $this->actingAs($this->admin)->get(route('admin.konsinyasi.index'));

    $props = inertiaProps($response);

    expect($props['partners'])->toHaveCount(1)
        ->and($props['partners'][0]['name'])->toBe($this->partner->name)
        ->and($props['deliveries']['data'])->toHaveCount(0);
});

it('returns paginated book options for the picker', function (): void {
    $book = makeStock(stok: 7);

    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.konsinyasi.options.books', ['search' => $book->judul]))
        ->assertSuccessful()
        ->json();

    // BookPicker butuh struktur paginator: {data, current_page, last_page, total}.
    expect($response['data'])->toHaveCount(1)
        ->and($response['total'])->toBe(1)
        ->and($response['current_page'])->toBe(1)
        ->and($response['data'][0]['judul'])->toBe($book->judul)
        ->and($response['data'][0]['stok'])->toBe(7);
});

it('records a delivery and moves stock out', function (): void {
    $book = makeStock(stok: 10);

    $this->actingAs($this->admin)
        ->post(route('admin.konsinyasi.deliveries.store'), [
            'customer_id' => $this->partner->id,
            'warehouse_id' => Warehouse::default()->id,
            'delivery_date' => '2026-08-20',
            'items' => [
                ['book_id' => $book->id, 'qty' => 4],
            ],
        ])
        ->assertRedirect();

    $book->refresh();

    expect($book->stok)->toBe(6)
        ->and(ConsignmentDelivery::count())->toBe(1)
        ->and(ConsignmentDelivery::first()->items()->count())->toBe(1)
        ->and(InventoryMovement::where('type', 'out')->where('reference', 'like', 'KSN-D-%')->exists())->toBeTrue();
});

it('rejects delivery for non-bazaf customer', function (): void {
    $book = makeStock();

    $this->actingAs($this->admin)
        ->post(route('admin.konsinyasi.deliveries.store'), [
            'customer_id' => $this->customerBiasa->id,
            'delivery_date' => '2026-08-20',
            'items' => [
                ['book_id' => $book->id, 'qty' => 1],
            ],
        ])
        ->assertSessionHasErrors('customer_id');

    expect(ConsignmentDelivery::count())->toBe(0);
});

it('blocks delivery exceeding available stock and rolls back', function (): void {
    $book = makeStock(stok: 3);

    $this->actingAs($this->admin)
        ->post(route('admin.konsinyasi.deliveries.store'), [
            'customer_id' => $this->partner->id,
            'warehouse_id' => Warehouse::default()->id,
            'delivery_date' => '2026-08-20',
            'items' => [
                ['book_id' => $book->id, 'qty' => 10],
            ],
        ])
        ->assertSessionHasErrors('items');

    $book->refresh();

    expect($book->stok)->toBe(3)
        ->and(ConsignmentDelivery::count())->toBe(0);
});

it('lists all consignment transactions in the laporan preview', function (): void {
    $bookA = makeStock(stok: 10);
    $bookB = makeStock(stok: 8);

    actingDeliver($this->admin, $this->partner->id, [
        ['book_id' => $bookA->id, 'qty' => 5],
        ['book_id' => $bookB->id, 'qty' => 2],
    ]);
    actingSale($this->admin, $this->partner->id, [
        ['book_id' => $bookA->id, 'qty' => 2, 'price' => 40000],
    ]);
    actingReturn($this->admin, $this->partner->id, $bookB->id, 1);

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.konsinyasi.laporan')));

    // Preview berisi baris per item: 2 serah terima + 1 laku + 1 retur.
    expect($props['rows']['total'])->toBe(4);

    $jenis = collect($props['rows']['data'])->pluck('jenis')->toArray();

    expect($jenis)->toContain('Serah Terima')
        ->and($jenis)->toContain('Laku')
        ->and($jenis)->toContain('Retur');

    // Ringkasan stok dihitung dari seluruh data.
    expect($props['stockSummary']['delivered_qty'])->toBe(7)
        ->and($props['stockSummary']['sold_qty'])->toBe(2)
        ->and($props['stockSummary']['returned_qty'])->toBe(1)
        ->and($props['stockSummary']['remaining_qty'])->toBe(4);
});

it('filters laporan by transaction type', function (): void {
    $book = makeStock(stok: 10);
    actingDeliver($this->admin, $this->partner->id, [
        ['book_id' => $book->id, 'qty' => 5],
    ]);
    actingSale($this->admin, $this->partner->id, [
        ['book_id' => $book->id, 'qty' => 2, 'price' => 40000],
    ]);

    $props = inertiaProps(
        $this->actingAs($this->admin)
            ->get(route('admin.konsinyasi.laporan', ['jenis' => 'laku'])),
    );

    expect($props['rows']['total'])->toBe(1)
        ->and($props['rows']['data'][0]['jenis'])->toBe('Laku')
        ->and($props['rows']['data'][0]['subtotal'])->toBe(80000);
});

it('exports the konsinyasi report as xlsx', function (): void {
    $book = makeStock(stok: 10);
    actingDeliver($this->admin, $this->partner->id, [
        ['book_id' => $book->id, 'qty' => 5],
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.konsinyasi.laporan.export'));

    expect($response->headers->get('content-type'))
        ->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('records sale with automatic receivable', function (): void {
    $book = makeStock(stok: 10, harga: 30000);
    actingDeliver($this->admin, $this->partner->id, [
        ['book_id' => $book->id, 'qty' => 6],
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.konsinyasi.sales.store'), [
            'customer_id' => $this->partner->id,
            'sale_date' => '2026-08-22',
            'items' => [
                ['book_id' => $book->id, 'qty' => 2, 'price' => 30000],
            ],
        ])
        ->assertRedirect();

    $sale = ConsignmentSale::firstOrFail();

    expect($book->fresh()->stok)->toBe(4) // Stok tidak berubah lagi saat laku.
        ->and($sale->items()->count())->toBe(1)
        ->and($sale->receivable_id)->not->toBeNull()
        ->and(Receivable::find($sale->receivable_id)?->amount)->toBe(60000);
});

it('blocks sale exceeding remaining consignment', function (): void {
    $book = makeStock(stok: 10);
    actingDeliver($this->admin, $this->partner->id, [
        ['book_id' => $book->id, 'qty' => 3],
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.konsinyasi.sales.store'), [
            'customer_id' => $this->partner->id,
            'sale_date' => '2026-08-22',
            'items' => [
                ['book_id' => $book->id, 'qty' => 4, 'price' => 30000],
            ],
        ])
        ->assertSessionHasErrors('items');

    expect(ConsignmentSale::count())->toBe(0)
        ->and(Receivable::count())->toBe(0);
});

it('exposes titipan options per partner', function (): void {
    $book = makeStock(harga: 25000);
    actingDeliver($this->admin, $this->partner->id, [
        ['book_id' => $book->id, 'qty' => 7],
    ]);
    actingSale($this->admin, $this->partner->id, [
        ['book_id' => $book->id, 'qty' => 2, 'price' => 25000],
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson(route('admin.konsinyasi.options.titipan', ['user' => $this->partner->id]))
        ->assertSuccessful()
        ->json();

    expect($response)->toHaveCount(1)
        ->and($response[0]['sisa'])->toBe(5)
        ->and($response[0]['harga'])->toBe(25000);
});

it('records return and restores stock', function (): void {
    $book = makeStock(stok: 10);
    actingDeliver($this->admin, $this->partner->id, [
        ['book_id' => $book->id, 'qty' => 5],
    ]);

    $stockBefore = $book->fresh()->stok; // 5

    $this->actingAs($this->admin)
        ->post(route('admin.konsinyasi.returns.store'), [
            'customer_id' => $this->partner->id,
            'book_id' => $book->id,
            'qty' => 2,
            'return_date' => '2026-08-23',
        ])
        ->assertRedirect();

    expect($book->fresh()->stok)->toBe($stockBefore + 2)
        ->and(ConsignmentReturn::count())->toBe(1);
});

it('blocks return exceeding remaining consignment', function (): void {
    $book = makeStock(stok: 10);
    actingDeliver($this->admin, $this->partner->id, [
        ['book_id' => $book->id, 'qty' => 2],
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.konsinyasi.returns.store'), [
            'customer_id' => $this->partner->id,
            'book_id' => $book->id,
            'qty' => 3,
            'return_date' => '2026-08-23',
        ])
        ->assertSessionHasErrors('qty');

    expect(ConsignmentReturn::count())->toBe(0);
});

it('deletes an untouched delivery and restores stock', function (): void {
    $book = makeStock(stok: 10);
    actingDeliver($this->admin, $this->partner->id, [
        ['book_id' => $book->id, 'qty' => 4],
    ]);

    $delivery = ConsignmentDelivery::firstOrFail();

    $this->actingAs($this->admin)
        ->delete(route('admin.konsinyasi.deliveries.destroy', ['delivery' => $delivery->id]))
        ->assertRedirect();

    expect(ConsignmentDelivery::count())->toBe(0)
        ->and($book->fresh()->stok)->toBe(10);
});

it('blocks deleting a delivery that already has sales', function (): void {
    $book = makeStock(stok: 10);
    actingDeliver($this->admin, $this->partner->id, [
        ['book_id' => $book->id, 'qty' => 4],
    ]);
    actingSale($this->admin, $this->partner->id, [
        ['book_id' => $book->id, 'qty' => 1, 'price' => 30000],
    ]);

    $delivery = ConsignmentDelivery::firstOrFail();

    $this->actingAs($this->admin)
        ->delete(route('admin.konsinyasi.deliveries.destroy', ['delivery' => $delivery->id]));

    expect(ConsignmentDelivery::count())->toBe(1);
});

it('deletes an unpaid sale together with its receivable', function (): void {
    $book = makeStock(stok: 10);
    actingDeliver($this->admin, $this->partner->id, [
        ['book_id' => $book->id, 'qty' => 4],
    ]);
    actingSale($this->admin, $this->partner->id, [
        ['book_id' => $book->id, 'qty' => 1, 'price' => 30000],
    ]);

    $sale = ConsignmentSale::firstOrFail();

    $this->actingAs($this->admin)
        ->delete(route('admin.konsinyasi.sales.destroy', ['sale' => $sale->id]))
        ->assertRedirect();

    expect(ConsignmentSale::count())->toBe(0)
        ->and(Receivable::count())->toBe(0);
});

it('blocks deleting a sale whose receivable is paid', function (): void {
    $book = makeStock(stok: 10);
    actingDeliver($this->admin, $this->partner->id, [
        ['book_id' => $book->id, 'qty' => 4],
    ]);
    actingSale($this->admin, $this->partner->id, [
        ['book_id' => $book->id, 'qty' => 1, 'price' => 30000],
    ]);

    $sale = ConsignmentSale::firstOrFail();
    $receivable = Receivable::findOrFail($sale->receivable_id);
    $receivable->update(['paid_amount' => 10000]);

    $this->actingAs($this->admin)
        ->delete(route('admin.konsinyasi.sales.destroy', ['sale' => $sale->id]));

    expect(ConsignmentSale::count())->toBe(1)
        ->and(Receivable::count())->toBe(1);
});
