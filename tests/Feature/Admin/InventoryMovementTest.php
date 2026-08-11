<?php

use App\Enums\MovementType;
use App\Models\Book;
use App\Models\User;
use App\Models\Warehouse;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $this->sidoarjo = Warehouse::firstOrCreate(['kode' => 'sidoarjo'], ['nama' => 'Sidoarjo', 'is_active' => true]);
    $this->defect = Warehouse::firstOrCreate(['kode' => 'defect'], ['nama' => 'Defect', 'is_defect' => true]);
});

it('records a stock-in movement via the admin endpoint', function (): void {
    $book = Book::factory()->create(['stok' => 0]);

    $this->actingAs($this->admin)
        ->post(route('admin.inventory.movements.store'), [
            'book_id' => $book->id,
            'type' => MovementType::In->value,
            'qty' => 5,
            'to_warehouse' => $this->malang->id,
        ])
        ->assertRedirect();

    expect($book->fresh()->stok)->toBe(5);
});

it('records a stock-out and transfer movement', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create();

    $this->actingAs($this->admin)
        ->post(route('admin.inventory.movements.store'), [
            'book_id' => $book->id,
            'type' => MovementType::Transfer->value,
            'qty' => 4,
            'from_warehouse' => $this->malang->id,
            'to_warehouse' => $this->sidoarjo->id,
        ])
        ->assertRedirect();

    expect($book->fresh()->stok)->toBe(10)
        ->and(DB::table('inventory_movements')->where('type', MovementType::Transfer->value)->count())->toBe(1);

    $this->actingAs($this->admin)
        ->post(route('admin.inventory.movements.store'), [
            'book_id' => $book->id,
            'type' => MovementType::Out->value,
            'qty' => 3,
            'from_warehouse' => $this->sidoarjo->id,
        ])
        ->assertRedirect();

    expect($book->fresh()->stok)->toBe(7);
});

it('records a movement for a specific edition', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create();
    $edition = $book->editions()->firstOrFail();

    $this->actingAs($this->admin)
        ->post(route('admin.inventory.movements.store'), [
            'book_id' => $book->id,
            'book_edition_id' => $edition->id,
            'type' => MovementType::In->value,
            'qty' => 2,
            'to_warehouse' => $this->malang->id,
        ])
        ->assertRedirect();

    expect($book->fresh()->stok)->toBe(7);
});

it('moves damaged stock to the defect warehouse', function (): void {
    $book = Book::factory()->withStock(malang: 10, defect: 2)->create();

    $this->actingAs($this->admin)
        ->post(route('admin.inventory.movements.store'), [
            'book_id' => $book->id,
            'type' => MovementType::Defect->value,
            'qty' => 2,
            'from_warehouse' => $this->malang->id,
            'to_warehouse' => $this->defect->id,
        ])
        ->assertRedirect();

    expect($book->fresh()->stok)->toBe(8);
});

it('validates movement input', function (): void {
    $book = Book::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.inventory.movements.store'), [
            'book_id' => $book->id,
            'type' => 'asal-asalan',
            'qty' => 0,
        ])
        ->assertSessionHasErrors(['type', 'qty']);

    $this->actingAs($this->admin)
        ->post(route('admin.inventory.movements.store'), [
            'type' => MovementType::In->value,
            'qty' => 1,
        ])
        ->assertSessionHasErrors(['book_id']);
});

it('rejects a movement that exceeds available stock without crashing', function (): void {
    $book = Book::factory()->withStock(malang: 2)->create();

    $this->actingAs($this->admin)
        ->post(route('admin.inventory.movements.store'), [
            'book_id' => $book->id,
            'type' => MovementType::Out->value,
            'qty' => 99,
            'from_warehouse' => $this->malang->id,
        ])
        ->assertRedirect();

    // Stok tidak berubah, mutasi tidak tercatat.
    expect($book->fresh()->stok)->toBe(2)
        ->and(DB::table('inventory_movements')->count())->toBe(0);
});

it('blocks customers from the movement endpoint', function (): void {
    $customer = User::factory()->customer()->create();
    $book = Book::factory()->create();

    $this->actingAs($customer)
        ->post(route('admin.inventory.movements.store'), [
            'book_id' => $book->id,
            'type' => MovementType::In->value,
            'qty' => 1,
            'to_warehouse' => $this->malang->id,
        ])
        ->assertForbidden();
});
