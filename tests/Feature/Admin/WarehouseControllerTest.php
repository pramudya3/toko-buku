<?php

use App\Models\Book;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Models\Warehouse;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('lists warehouses', function (): void {
    Warehouse::factory()->create(['nama' => 'Surabaya', 'kode' => 'surabaya']);

    $props = inertiaProps($this->get(route('admin.warehouses.index')));

    expect(collect($props['warehouses']['data'])->pluck('nama'))->toContain('Surabaya');
});

it('creates a warehouse', function (): void {
    $this->post(route('admin.warehouses.store'), [
        'kode' => 'surabaya',
        'nama' => 'Surabaya',
        'alamat' => 'Jl. Ahmad Yani 1',
        'is_active' => true,
    ])->assertRedirect(route('admin.warehouses.index'));

    expect(Warehouse::where('kode', 'surabaya')->exists())->toBeTrue();
});

it('validates warehouse kode format and uniqueness', function (): void {
    $this->post(route('admin.warehouses.store'), [
        'kode' => 'Kode Buruk!',
        'nama' => 'X',
    ])->assertSessionHasErrors('kode');

    $this->post(route('admin.warehouses.store'), [
        'kode' => 'malang',
        'nama' => 'Duplikat',
    ])->assertSessionHasErrors('kode');
});

it('updates a warehouse but keeps the kode immutable', function (): void {
    $warehouse = Warehouse::factory()->create(['nama' => 'Gudang Lama', 'kode' => 'lama']);

    $this->put(route('admin.warehouses.update', $warehouse), [
        'nama' => 'Gudang Baru',
        'is_active' => false,
    ])->assertRedirect(route('admin.warehouses.index'));

    $warehouse->refresh();

    expect($warehouse->nama)->toBe('Gudang Baru')
        ->and($warehouse->is_active)->toBeFalse()
        ->and($warehouse->kode)->toBe('lama');
});

it('allows only one defect warehouse', function (): void {
    $defect = Warehouse::query()->defect()->firstOrFail();
    $other = Warehouse::factory()->create();

    $this->put(route('admin.warehouses.update', $other), [
        'nama' => $other->nama,
        'is_defect' => true,
    ])->assertSessionHasErrors('is_defect');

    expect($other->fresh()->is_defect)->toBeFalse();
    expect($defect->fresh()->is_defect)->toBeTrue();
});

it('blocks creating a second defect warehouse', function (): void {
    Warehouse::query()->defect()->firstOrFail();

    $this->post(route('admin.warehouses.store'), [
        'kode' => 'defect2',
        'nama' => 'Defect 2',
        'is_defect' => true,
    ])->assertSessionHasErrors('is_defect');

    expect(Warehouse::where('kode', 'defect2')->exists())->toBeFalse();
});

it('blocks deleting a warehouse that still holds stock', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create();
    $malang = Warehouse::where('kode', 'malang')->firstOrFail();

    $this->delete(route('admin.warehouses.destroy', $malang))->assertRedirect();

    expect(Warehouse::find($malang->id))->not->toBeNull();
});

it('blocks deleting a warehouse with movement history', function (): void {
    $book = Book::factory()->withStock(malang: 1)->create();
    $malang = Warehouse::where('kode', 'malang')->firstOrFail();
    $sidoarjo = Warehouse::where('kode', 'sidoarjo')->firstOrFail();

    InventoryMovement::create([
        'book_id' => $book->id,
        'from_warehouse_id' => $malang->id,
        'to_warehouse_id' => $sidoarjo->id,
        'qty' => 1,
        'type' => 'transfer',
    ]);

    $this->delete(route('admin.warehouses.destroy', $sidoarjo))->assertRedirect();

    expect(Warehouse::find($sidoarjo->id))->not->toBeNull();
});

it('soft deletes an empty warehouse and restores it', function (): void {
    $warehouse = Warehouse::factory()->create(['kode' => 'surabaya', 'nama' => 'Surabaya']);

    $this->delete(route('admin.warehouses.destroy', $warehouse))->assertRedirect();

    expect(Warehouse::find($warehouse->id))->toBeNull();

    $this->post(route('admin.warehouses.restore', $warehouse));

    expect(Warehouse::find($warehouse->id))->not->toBeNull();
});

it('blocks non-admin customers from warehouse routes', function (): void {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.warehouses.index'))
        ->assertForbidden();

    $this->actingAs($customer)
        ->post(route('admin.warehouses.store'), ['kode' => 'x', 'nama' => 'X'])
        ->assertForbidden();
});

it('uses the new warehouse as movement destination', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create();
    $surabaya = Warehouse::factory()->create(['kode' => 'surabaya', 'nama' => 'Surabaya']);

    $this->post(route('admin.inventory.movements.store'), [
        'book_id' => $book->id,
        'type' => 'in',
        'to_warehouse' => $surabaya->id,
        'qty' => 3,
    ])->assertRedirect();

    expect($book->fresh()->inventoryStocks()->where('warehouse_id', $surabaya->id)->sum('qty'))->toBe(3)
        ->and($book->fresh()->stok)->toBe(13);
});
