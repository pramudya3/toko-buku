<?php

use App\Enums\MovementType;
use App\Models\Book;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryMovementXlsxExporter;
use App\Services\InventoryService;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->service = app(InventoryService::class);
    $this->malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang']);
    $this->sidoarjo = Warehouse::firstOrCreate(['kode' => 'sidoarjo'], ['nama' => 'Sidoarjo']);
    $this->defect = Warehouse::firstOrCreate(['kode' => 'defect'], ['nama' => 'Defect', 'is_defect' => true]);
});

it('lists movements with edition detail on the report page', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create();
    $edition = $book->editions()->orderBy('cetakan_ke')->first();

    $this->service->move(
        book: $book,
        type: MovementType::In,
        qty: 3,
        to: $this->malang,
        userId: $this->admin->id,
        notes: 'Stok masuk dari penerbit',
        edition: $edition,
    );

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.inventory-reports.index')));

    expect($props['movements']['total'])->toBe(1)
        ->and($props['movements']['data'][0]['book']['judul'])->toBe($book->judul)
        ->and($props['movements']['data'][0]['edition']['cetakan_ke'])->toBe(1)
        ->and($props['movements']['data'][0]['qty'])->toBe(3);
});

it('filters movements by type', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create();
    $edition = $book->editions()->orderBy('cetakan_ke')->first();

    $this->service->move(book: $book, type: MovementType::In, qty: 2, to: $this->malang, userId: $this->admin->id, edition: $edition);
    $this->service->move(book: $book, type: MovementType::Transfer, qty: 1, from: $this->malang, to: $this->sidoarjo, userId: $this->admin->id, edition: $edition);

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.inventory-reports.index', [
        'type' => MovementType::Transfer->value,
    ])));

    expect($props['movements']['total'])->toBe(1)
        ->and($props['movements']['data'][0]['type'])->toBe(MovementType::Transfer->value);
});

it('filters movements by warehouse', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create();
    $edition = $book->editions()->orderBy('cetakan_ke')->first();

    $this->service->move(book: $book, type: MovementType::In, qty: 2, to: $this->sidoarjo, userId: $this->admin->id, edition: $edition);

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.inventory-reports.index', [
        'warehouse_id' => $this->malang->id,
    ])));

    expect($props['movements']['total'])->toBe(0);

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.inventory-reports.index', [
        'warehouse_id' => $this->sidoarjo->id,
    ])));

    expect($props['movements']['total'])->toBe(1);
});

it('exports movement report as xlsx with edition column', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create();
    $edition = $book->editions()->orderBy('cetakan_ke')->first();

    $this->service->move(
        book: $book,
        type: MovementType::In,
        qty: 5,
        to: $this->malang,
        userId: $this->admin->id,
        reference: 'PO-2026-001',
        notes: 'Pembelian dari penerbit',
        edition: $edition,
    );

    $response = $this->actingAs($this->admin)
        ->get(route('admin.inventory-reports.export', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain('laporan-mutasi_')->toEndWith('.xlsx');

    $rows = app(InventoryMovementXlsxExporter::class)->buildRows(now()->startOfMonth(), now());

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['buku'])->toBe($book->judul)
        ->and($rows[0]['cetakan'])->toBe('Cetakan ke-1')
        ->and($rows[0]['tipe'])->toBe('Stok Masuk')
        ->and($rows[0]['dari_gudang'])->toBe('')
        ->and($rows[0]['ke_gudang'])->toBe('Malang')
        ->and($rows[0]['qty'])->toBe(5)
        ->and($rows[0]['ref_code'])->toBe('PO-2026-001')
        ->and($rows[0]['petugas'])->toBe($this->admin->name);
});

it('builds empty rows when no movements match', function (): void {
    $rows = app(InventoryMovementXlsxExporter::class)->buildRows(
        now()->subDays(30),
        now()->subDays(29),
        MovementType::In->value,
    );

    expect($rows)->toBeEmpty();
});
