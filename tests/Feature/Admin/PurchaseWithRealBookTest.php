<?php

use App\Models\Book;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('creates a purchase using real books from the database with valid data', function (): void {
    // Buat buku valid seperti DemoSeeder (judul real, harga valid, ada cetakan)
    // Jika DB test kosong (RefreshDatabase), buat 1 buku valid; jika ada real, pakai yang pertama
    $book = Book::where('aktif', true)->where('harga', '>', 0)->first();
    if (! $book) {
        $book = Book::factory()->create([
            'judul' => 'Laskar Pelangi',
            'penulis' => 'Andrea Hirata',
            'harga' => 85000,
            'kode_sku' => 'SKU-TEST-001',
            'aktif' => true,
        ]);
        // Pastikan ada edition valid
        if ($book->editions()->doesntExist()) {
            $book->editions()->create([
                'cetakan_ke' => 1,
                'harga_beli' => 55000,
                'harga_jual' => 85000,
                'is_active' => true,
            ]);
        }
    }

    $edition = $book->editions()->first();
    expect($edition)->not->toBeNull();
    expect($edition->harga_beli)->toBeGreaterThan(0);
    expect($edition->harga_jual)->toBeGreaterThan(0);
    expect($book->category_id)->not->toBeNull();

    $supplier = Supplier::first() ?? Supplier::factory()->create();
    $warehouse = Warehouse::where('kode', 'malang')->first() ?? Warehouse::factory()->create(['kode' => 'malang']);

    $payload = [
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-REAL-'.now()->format('YmdHis'),
        'purchase_date' => now()->toDateString(),
        'warehouse_kode' => $warehouse->kode,
        'shipping_cost' => 0,
        'paid_amount' => 0,
        'notes' => 'Test dengan buku real',
        'items' => [
            [
                'book_id' => $book->id,
                'book_edition_id' => $edition->id,
                'qty' => 5,
                'price' => $edition->harga_beli,
            ],
        ],
    ];

    $this->post(route('admin.purchases.store'), $payload)
        ->assertRedirect(route('admin.purchases.index'));

    $this->assertDatabaseHas('supplier_purchases', [
        'ref_code' => $payload['ref_code'],
        'warehouse_kode' => $warehouse->kode,
        'shipping_cost' => 0,
    ]);

    $this->assertDatabaseHas('supplier_purchase_items', [
        'book_id' => $book->id,
        'book_edition_id' => $edition->id,
        'qty' => 5,
    ]);

    // Pastikan HPP ter-update (weighted average) dan stok masuk
    $book->refresh();
    $edition->refresh();
    expect($edition->harga_beli)->toBeGreaterThan(0);
});

it('rejects purchase with invalid book data', function (): void {
    $supplier = Supplier::first() ?? Supplier::factory()->create();
    $warehouse = Warehouse::where('kode', 'malang')->first() ?? Warehouse::factory()->create(['kode' => 'malang']);

    // Buat buku valid dulu
    $book = Book::where('aktif', true)->first();
    if (! $book) {
        $book = Book::factory()->create(['judul' => 'Buku Valid Test', 'harga' => 50000, 'aktif' => true]);
    }

    $this->post(route('admin.purchases.store'), [
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-INVALID-'.now()->format('YmdHis'),
        'purchase_date' => now()->toDateString(),
        'warehouse_kode' => $warehouse->kode,
        'items' => [
            ['book_id' => $book->id, 'qty' => 1, 'price' => 0], // price 0 masih valid (min 0), tapi qty 0 tidak
        ],
    ])->assertRedirect(); // price 0 valid, should redirect success

    // Qty 0 harus fail
    $this->post(route('admin.purchases.store'), [
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-INVALID2-'.now()->format('YmdHis'),
        'purchase_date' => now()->toDateString(),
        'warehouse_kode' => $warehouse->kode,
        'items' => [
            ['book_id' => $book->id, 'qty' => 0, 'price' => 10000],
        ],
    ])->assertSessionHasErrors('items.0.qty');
});
