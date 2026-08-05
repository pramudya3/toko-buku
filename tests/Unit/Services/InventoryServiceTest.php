<?php

use App\Enums\MovementType;
use App\Enums\Warehouse;
use App\Models\Book;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Services\InventoryService;
use App\Models\User;

beforeEach(function (): void {
    $this->service = app(InventoryService::class);
    $this->user = User::factory()->admin()->create();
});

it('records stock in movement and syncs the book aggregate (INV-06)', function (): void {
    $book = Book::factory()->create();

    $movement = $this->service->move($book, MovementType::In, 10, to: Warehouse::Malang, userId: $this->user->id);

    expect($movement)->toBeInstanceOf(InventoryMovement::class)
        ->and($book->fresh()->stok)->toBe(10)
        ->and($book->inventoryStock->stock_malang)->toBe(10)
        ->and(InventoryMovement::count())->toBe(1);
});

it('transfers stock between warehouses and records audit trail (INV-02, INV-03)', function (): void {
    $book = Book::factory()->withStock(malang: 10, sidoarjo: 0)->create();

    $this->service->move(
        book: $book,
        type: MovementType::Transfer,
        qty: 4,
        from: Warehouse::Malang,
        to: Warehouse::Sidoarjo,
        userId: $this->user->id,
        notes: 'Kirim ke gudang Sidoarjo',
    );

    $stock = $book->fresh()->inventoryStock;

    expect($stock->stock_malang)->toBe(6)
        ->and($stock->stock_sidoarjo)->toBe(4)
        ->and($book->fresh()->stok)->toBe(10)
        ->and(InventoryMovement::latest()->first()->type)->toBe(MovementType::Transfer)
        ->and(InventoryMovement::latest()->first()->user_id)->toBe($this->user->id);
});

it('rejects movements that would make stock negative (INV-04, BR-07)', function (): void {
    $book = Book::factory()->withStock(malang: 2, sidoarjo: 0)->create();

    expect(fn () => $this->service->move($book, MovementType::Out, 3, from: Warehouse::Malang, userId: $this->user->id))
        ->toThrow(RuntimeException::class);

    expect($book->fresh()->stok)->toBe(2)
        ->and(InventoryMovement::count())->toBe(0);
});

it('never sells defect stock (INV-05, BR-07)', function (): void {
    $book = Book::factory()->withStock(malang: 5, sidoarjo: 0, defect: 3)->create();

    expect(fn () => $this->service->assertSufficientStock($book, Warehouse::Defect, 1))
        ->toThrow(RuntimeException::class);

    expect($this->service->availableStock($book))->toBe(5);
});

it('moves stock to defect via defect movement', function (): void {
    $book = Book::factory()->withStock(malang: 5, sidoarjo: 0, defect: 0)->create();

    $this->service->move(
        book: $book,
        type: MovementType::Defect,
        qty: 2,
        from: Warehouse::Malang,
        to: Warehouse::Defect,
        userId: $this->user->id,
    );

    $stock = $book->fresh()->inventoryStock;

    expect($stock->stock_malang)->toBe(3)
        ->and($stock->stock_defect)->toBe(2)
        ->and($book->fresh()->stok)->toBe(3);
});

it('rejects invalid warehouse combinations for each movement type', function (): void {
    $book = Book::factory()->withStock(malang: 5, sidoarjo: 2, defect: 3)->create();

    $invalidMovements = [
        [MovementType::In, null, Warehouse::Defect],
        [MovementType::Out, Warehouse::Defect, null],
        [MovementType::Transfer, Warehouse::Malang, Warehouse::Defect],
        [MovementType::Defect, Warehouse::Defect, Warehouse::Malang],
    ];

    foreach ($invalidMovements as [$type, $from, $to]) {
        expect(fn () => $this->service->move($book, $type, 1, $from, $to))
            ->toThrow(RuntimeException::class);
    }

    expect($book->fresh()->stok)->toBe(7)
        ->and(InventoryMovement::count())->toBe(0);
});

it('rejects non-positive quantities', function (): void {
    $book = Book::factory()->withStock()->create();

    expect(fn () => $this->service->move($book, MovementType::In, 0, to: Warehouse::Malang))
        ->toThrow(RuntimeException::class);
});

it('stores movements with order reference (audit trail)', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create();
    $order = \App\Models\Order::factory()->create();

    $this->service->move(
        book: $book,
        type: MovementType::Out,
        qty: 2,
        from: Warehouse::Malang,
        reference: (string) $order->id,
        userId: $this->user->id,
    );

    expect(InventoryMovement::latest()->first()->reference)->toBe((string) $order->id);
});
