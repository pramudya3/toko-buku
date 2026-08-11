<?php

use App\Enums\MovementType;
use App\Models\Book;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;

beforeEach(function (): void {
    $this->service = app(InventoryService::class);
    $this->user = User::factory()->admin()->create();
    // Migration men-seed gudang bawaan (malang/sidoarjo/defect) — pakai firstOrCreate.
    $this->malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang']);
    $this->sidoarjo = Warehouse::firstOrCreate(['kode' => 'sidoarjo'], ['nama' => 'Sidoarjo']);
    $this->defect = Warehouse::firstOrCreate(['kode' => 'defect'], ['nama' => 'Defect', 'is_defect' => true]);
});

it('records stock in movement and syncs the book aggregate (INV-06)', function (): void {
    $book = Book::factory()->create();

    $movement = $this->service->move($book, MovementType::In, 10, to: $this->malang, userId: $this->user->id);

    expect($movement)->toBeInstanceOf(InventoryMovement::class)
        ->and($book->fresh()->stok)->toBe(10)
        ->and($book->fresh()->inventoryStocks()->where('warehouse_id', $this->malang->id)->first()->qty)->toBe(10)
        ->and(InventoryMovement::count())->toBe(1);
});

it('transfers stock between warehouses and records audit trail (INV-02, INV-03)', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create();

    $this->service->move(
        book: $book,
        type: MovementType::Transfer,
        qty: 4,
        from: $this->malang,
        to: $this->sidoarjo,
        userId: $this->user->id,
        notes: 'Kirim ke gudang Sidoarjo',
    );

    $stock = $book->fresh()->inventoryStocks;

    expect($stock->firstWhere('warehouse_id', $this->malang->id)->qty)->toBe(6)
        ->and($stock->firstWhere('warehouse_id', $this->sidoarjo->id)->qty)->toBe(4)
        ->and($book->fresh()->stok)->toBe(10)
        ->and(InventoryMovement::latest()->first()->type)->toBe(MovementType::Transfer)
        ->and(InventoryMovement::latest()->first()->user_id)->toBe($this->user->id);
});

it('rejects movements that would make stock negative (INV-04, BR-07)', function (): void {
    $book = Book::factory()->withStock(malang: 2)->create();

    expect(fn () => $this->service->move($book, MovementType::Out, 3, from: $this->malang, userId: $this->user->id))
        ->toThrow(RuntimeException::class);

    expect($book->fresh()->stok)->toBe(2)
        ->and(InventoryMovement::count())->toBe(0);
});

it('never sells defect stock (INV-05, BR-07)', function (): void {
    $book = Book::factory()->withStock(malang: 5, defect: 3)->create();

    expect(fn () => $this->service->assertSufficientStock($book, $this->defect, 1))
        ->toThrow(RuntimeException::class);

    expect($this->service->availableStock($book))->toBe(5);
});

it('moves stock to defect via defect movement', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create();

    $this->service->move(
        book: $book,
        type: MovementType::Defect,
        qty: 2,
        from: $this->malang,
        to: $this->defect,
        userId: $this->user->id,
    );

    $stock = $book->fresh()->inventoryStocks;

    expect($stock->firstWhere('warehouse_id', $this->malang->id)->qty)->toBe(3)
        ->and($stock->firstWhere('warehouse_id', $this->defect->id)->qty)->toBe(2)
        ->and($book->fresh()->stok)->toBe(3);
});

it('rejects invalid warehouse combinations for each movement type', function (): void {
    $book = Book::factory()->withStock(malang: 5, sidoarjo: 2, defect: 3)->create();

    $invalidMovements = [
        [MovementType::In, null, $this->defect],
        [MovementType::Out, $this->defect, null],
        [MovementType::Transfer, $this->malang, $this->defect],
        [MovementType::Defect, $this->defect, $this->malang],
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

    expect(fn () => $this->service->move($book, MovementType::In, 0, to: $this->malang))
        ->toThrow(RuntimeException::class);
});

it('stores movements with order reference (audit trail)', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create();
    $order = Order::factory()->create();

    $this->service->move(
        book: $book,
        type: MovementType::Out,
        qty: 2,
        from: $this->malang,
        reference: (string) $order->id,
        userId: $this->user->id,
    );

    expect(InventoryMovement::latest()->first()->reference)->toBe((string) $order->id);
});

it('moves stock per edition and syncs aggregates from edition sums', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create();
    $edition1 = $book->editions()->orderBy('cetakan_ke')->first();
    $edition2 = $book->editions()->create([
        'cetakan_ke' => 2,
        'harga_beli' => 33000,
        'harga_jual' => 43000,
        'is_active' => false,
    ]);

    // Stok masuk ke cetakan ke-2 — tidak menyentuh cetakan ke-1.
    $this->service->move(
        book: $book,
        type: MovementType::In,
        qty: 3,
        to: $this->malang,
        userId: $this->user->id,
        edition: $edition2,
    );

    expect($edition1->stocks()->sum('qty'))->toBe(5)
        ->and($edition2->stocks()->where('warehouse_id', $this->malang->id)->sum('qty'))->toBe(3)
        // Agregat buku = jumlah semua cetakan.
        ->and($book->fresh()->stok)->toBe(8)
        ->and($this->service->availableStock($book->fresh()))->toBe(8)
        // Audit trail menunjuk cetakan.
        ->and(InventoryMovement::latest()->first()->book_edition_id)->toBe($edition2->id);
});

it('deducts stock from the exact edition purchased', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create();
    $edition2 = $book->editions()->create([
        'cetakan_ke' => 2,
        'harga_beli' => 33000,
        'harga_jual' => 43000,
        'is_active' => false,
    ]);

    $this->service->move(
        book: $book,
        type: MovementType::In,
        qty: 4,
        to: $this->malang,
        userId: $this->user->id,
        edition: $edition2,
    );

    $order = Order::factory()->create();
    $order->items()->create([
        'book_id' => $book->id,
        'book_edition_id' => $edition2->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => 43000,
        'qty' => 2,
    ]);
    $order->update(['warehouse_origin' => 'malang']);

    $this->service->deductForOrder($order, $this->user->id);

    expect($edition2->stocks()->where('warehouse_id', $this->malang->id)->sum('qty'))->toBe(2)
        ->and($book->editions()->orderBy('cetakan_ke')->first()->stocks()->where('warehouse_id', $this->malang->id)->sum('qty'))->toBe(5)
        ->and($book->fresh()->stok)->toBe(7);
});

it('rejects edition movements when the edition belongs to another book', function (): void {
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();

    expect(fn () => $this->service->move(
        book: $bookA,
        type: MovementType::In,
        qty: 1,
        to: $this->malang,
        edition: $bookB->editions()->orderBy('cetakan_ke')->first(),
    ))->toThrow(RuntimeException::class);
});
