<?php

use App\Enums\MovementType;
use App\Models\Book;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('renders the stock adjustment page with history', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create();
    $malang = Warehouse::where('kode', 'malang')->firstOrFail();

    $this->service = app(InventoryService::class);
    $this->service->adjust(
        book: $book,
        warehouse: $malang,
        qty: 3,
        userId: $this->admin->id,
        notes: 'Koreksi opname Januari',
    );

    $response = $this->get(route('admin.inventory-adjustments.index'));
    $response->assertSuccessful();

    $props = inertiaProps($response);

    expect($props['warehouses'])->not->toBeEmpty()
        ->and(collect($props['adjustments'])->first()['qty'])->toBe(3);
});

it('records a positive adjustment and increases stock', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create();
    $malang = Warehouse::where('kode', 'malang')->firstOrFail();

    $this->post(route('admin.inventory-adjustments.store'), [
        'book_id' => $book->id,
        'warehouse_id' => $malang->id,
        'qty' => 3,
        'notes' => 'Koreksi opname Januari',
    ])->assertSessionHasNoErrors();

    $movement = InventoryMovement::latest()->first();

    expect($book->fresh()->stok)->toBe(8)
        ->and($movement->type)->toBe(MovementType::Adjustment)
        ->and($movement->qty)->toBe(3)
        ->and($movement->to_warehouse_id)->toBe($malang->id)
        ->and($movement->notes)->toBe('Koreksi opname Januari');
});

it('records a negative adjustment and decreases stock', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create();
    $malang = Warehouse::where('kode', 'malang')->firstOrFail();

    $this->post(route('admin.inventory-adjustments.store'), [
        'book_id' => $book->id,
        'warehouse_id' => $malang->id,
        'qty' => -2,
        'notes' => 'Selisih opname Januari',
    ])->assertSessionHasNoErrors();

    expect($book->fresh()->stok)->toBe(3)
        ->and(InventoryMovement::latest()->first()->qty)->toBe(-2);
});

it('returns paginated book options for the picker', function (): void {
    Book::factory()->count(25)->create();

    $this->getJson(route('admin.inventory-adjustments.options.books'))
        ->assertSuccessful()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('total', 25)
        ->assertJsonPath('last_page', 2)
        ->assertJsonPath('current_page', 1);

    $this->getJson(route('admin.inventory-adjustments.options.books', ['page' => 2]))
        ->assertSuccessful()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('current_page', 2);
});

it('filters paginated book options by search', function (): void {
    Book::factory()->count(3)->create();
    Book::factory()->create(['judul' => 'Buku Target Opname']);

    $this->getJson(route('admin.inventory-adjustments.options.books', ['search' => 'Target Opname']))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.judul', 'Buku Target Opname');
});

it('validates qty, warehouse and notes', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create();

    $this->post(route('admin.inventory-adjustments.store'), [
        'book_id' => $book->id,
    ])->assertSessionHasErrors(['warehouse_id', 'qty', 'notes']);

    $malang = Warehouse::where('kode', 'malang')->firstOrFail();

    $this->post(route('admin.inventory-adjustments.store'), [
        'book_id' => $book->id,
        'warehouse_id' => $malang->id,
        'qty' => 0,
        'notes' => 'X',
    ])->assertSessionHasErrors('qty');

    expect(InventoryMovement::count())->toBe(0);
});
