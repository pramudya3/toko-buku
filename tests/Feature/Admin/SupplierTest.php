<?php

use App\Enums\MovementType;
use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\InventoryMovement;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\SupplierPurchase;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\SupplierService;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('allows admin to create a supplier', function (): void {
    $this->post(route('admin.suppliers.store'), [
        'nama' => 'PT Penerbit Buku',
        'telepon' => '0812-3456-7890',
        'alamat' => 'Jl. Raya 1, Jakarta',
    ])->assertRedirect(route('admin.suppliers.index'));

    expect(Supplier::where('nama', 'PT Penerbit Buku')->exists())->toBeTrue();
});

it('validates supplier name', function (): void {
    $this->post(route('admin.suppliers.store'), ['nama' => ''])
        ->assertSessionHasErrors('nama');

    expect(Supplier::count())->toBe(0);
});

it('lists suppliers with their outstanding balance', function (): void {
    $supplier = Supplier::factory()->create(['nama' => 'CV Toko Buku']);

    $purchase = SupplierPurchase::factory()->create([
        'supplier_id' => $supplier->id,
        'total' => 500_000,
    ]);
    SupplierPayment::factory()->create([
        'supplier_id' => $supplier->id,
        'amount' => 200_000,
        'supplier_purchase_id' => $purchase->id,
    ]);
    SupplierReturn::factory()->create([
        'supplier_id' => $supplier->id,
        'total' => 50_000,
    ]);

    $response = $this->get(route('admin.suppliers.index'));
    $response->assertOk();

    $props = inertiaProps($response);
    $row = collect($props['suppliers']['data'])->firstWhere('id', $supplier->id);

    expect($row['purchase_total'])->toBe(500_000)
        ->and($row['return_total'])->toBe(50_000)
        ->and($row['payment_total'])->toBe(200_000)
        ->and($row['saldo_hutang'])->toBe(250_000);
});

it('blocks non-admin customers from supplier routes', function (): void {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.suppliers.index'))
        ->assertForbidden();

    $this->actingAs($customer)
        ->post(route('admin.suppliers.store'), ['nama' => 'X'])
        ->assertForbidden();
});

// ── Barang Masuk (pembelian) ──────────────────────────────────────

it('records a purchase: stock masuk, hutang bertambah, movement tercatat', function (): void {
    $supplier = Supplier::factory()->create(['nama' => 'Penerbit A']);
    $book = Book::factory()->withStock(malang: 0)->create(['judul' => 'Buku Pembelian', 'harga' => 50_000]);

    $this->post(route('admin.purchases.store'), [
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-20260810-001',
        'purchase_date' => '2026-08-10',
        'warehouse_kode' => 'malang',
        'items' => [
            ['book_id' => $book->id, 'qty' => 5, 'price' => 30_000],
        ],
    ])->assertRedirect(route('admin.purchases.index'));

    // Stok masuk gudang Malang
    expect(app(InventoryService::class)->availableStock($book->refresh()))->toBe(5)
        ->and($book->refresh()->stok)->toBe(5);

    // Audit trail inventori
    $movement = InventoryMovement::where('book_id', $book->id)->latest()->first();
    expect($movement->type->value)->toBe('in')
        ->and($movement->toWarehouse->kode)->toBe('malang')
        ->and($movement->reference)->toBe('PO-20260810-001');

    // Header pembelian + hutang
    $purchase = $supplier->purchases()->first();
    expect($purchase->total)->toBe(150_000)
        ->and(app(SupplierService::class)->saldoHutang($supplier))->toBe(150_000);
});

it('validates purchase: ref code unik, supplier wajib, item wajib ada', function (): void {
    $supplier = Supplier::factory()->create();
    $book = Book::factory()->create(['aktif' => true]);

    SupplierPurchase::factory()->create([
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-DUP',
    ]);

    $this->post(route('admin.purchases.store'), [
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-DUP',
        'purchase_date' => '2026-08-10',
        'items' => [['book_id' => $book->id, 'qty' => 1, 'price' => 10_000]],
    ])->assertSessionHasErrors('ref_code');

    $this->post(route('admin.purchases.store'), [
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-NEW',
        'purchase_date' => '2026-08-10',
        'items' => [],
    ])->assertSessionHasErrors('items');

    $this->post(route('admin.purchases.store'), [
        'ref_code' => 'PO-NO-SUPPLIER',
        'purchase_date' => '2026-08-10',
        'items' => [['book_id' => $book->id, 'qty' => 1, 'price' => 10_000]],
    ])->assertSessionHasErrors('supplier_id');
});

it('rejects duplicate books inside one purchase', function (): void {
    $supplier = Supplier::factory()->create();
    $book = Book::factory()->create(['aktif' => true]);

    $this->post(route('admin.purchases.store'), [
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-DUP-BOOK',
        'purchase_date' => '2026-08-10',
        'items' => [
            ['book_id' => $book->id, 'qty' => 1, 'price' => 10_000],
            ['book_id' => $book->id, 'qty' => 2, 'price' => 10_000],
        ],
    ])->assertSessionHasErrors('items.1.book_id');
});

it('records payment when paid amount is filled at purchase', function (): void {
    $supplier = Supplier::factory()->create();
    $book = Book::factory()->withStock(malang: 0)->create(['aktif' => true]);

    $this->post(route('admin.purchases.store'), [
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-PAID',
        'purchase_date' => '2026-08-10',
        'warehouse_kode' => 'malang',
        'paid_amount' => 100_000,
        'items' => [['book_id' => $book->id, 'qty' => 10, 'price' => 20_000]],
    ])->assertRedirect();

    $purchase = $supplier->purchases()->first();
    expect($purchase->total)->toBe(200_000)
        ->and($supplier->payments()->count())->toBe(1)
        ->and($supplier->payments()->first()->amount)->toBe(100_000)
        ->and($supplier->payments()->first()->supplier_purchase_id)->toBe($purchase->id)
        ->and(app(SupplierService::class)->saldoHutang($supplier))->toBe(100_000);
});

// ── Retur supplier ────────────────────────────────────────────────

it('records a defect return: stock defect berkurang, hutang berkurang', function (): void {
    $supplier = Supplier::factory()->create(['nama' => 'Penerbit B']);

    $book = Book::factory()->withStock(malang: 5, defect: 3)->create(['judul' => 'Buku Retur']);
    $purchase = SupplierPurchase::factory()->create([
        'supplier_id' => $supplier->id,
        'total' => 300_000,
    ]);

    $this->post(route('admin.supplier-returns.store'), [
        'supplier_id' => $supplier->id,
        'return_date' => '2026-08-11',
        'purchase_id' => $purchase->id,
        'items' => [
            ['book_id' => $book->id, 'qty' => 2, 'price' => 40_000, 'reason' => 'Halaman rusak', 'source' => 'defect'],
        ],
    ])->assertRedirect(route('admin.supplier-returns.index'));

    expect($book->refresh()->inventoryStocks()->whereHas('warehouse', fn ($q) => $q->where('is_defect', 1))->sum('qty'))->toBe(1);

    $return = $supplier->returns()->first();
    expect($return->total)->toBe(80_000)
        ->and($return->supplier_purchase_id)->toBe($purchase->id)
        ->and($return->items()->first()->reason)->toBe('Halaman rusak');

    // Hutang: 300.000 (beli) − 80.000 (retur)
    expect(app(SupplierService::class)->saldoHutang($supplier))->toBe(220_000);

    // Audit trail: stok defect keluar (retur supplier)
    $movement = InventoryMovement::where('book_id', $book->id)->latest()->first();
    expect($movement->type->value)->toBe('return')
        ->and($movement->fromWarehouse->kode)->toBe('defect');
});

it('records a return from normal stock with a reason', function (): void {
    $supplier = Supplier::factory()->create(['nama' => 'Penerbit C']);
    $book = Book::factory()->withStock(malang: 5, defect: 0)->create(['judul' => 'Buku Salah Kirim']);

    $this->post(route('admin.supplier-returns.store'), [
        'supplier_id' => $supplier->id,
        'return_date' => '2026-08-11',
        'items' => [
            ['book_id' => $book->id, 'qty' => 2, 'price' => 35_000, 'reason' => 'Salah kirim judul', 'source' => 'normal'],
        ],
    ])->assertRedirect(route('admin.supplier-returns.index'));

    // Stok normal berkurang
    expect(app(InventoryService::class)->availableStock($book->refresh()))->toBe(3)
        ->and($book->refresh()->stok)->toBe(3);

    $return = $supplier->returns()->first();
    expect($return->total)->toBe(70_000)
        ->and($return->items()->first()->reason)->toBe('Salah kirim judul');

    // Audit trail: stok keluar dari gudang normal
    $movement = InventoryMovement::where('book_id', $book->id)->latest()->first();
    expect($movement->type->value)->toBe('out')
        ->and($movement->fromWarehouse->kode)->toBe('malang')
        ->and($movement->notes)->toContain('Salah kirim judul');
});

it('rejects a return when qty exceeds defect stock', function (): void {
    $supplier = Supplier::factory()->create();
    $book = Book::factory()->withStock(malang: 5, defect: 1)->create(['aktif' => true]);

    $this->post(route('admin.supplier-returns.store'), [
        'supplier_id' => $supplier->id,
        'return_date' => '2026-08-11',
        'items' => [
            ['book_id' => $book->id, 'qty' => 5, 'price' => 10_000, 'reason' => 'Rusak', 'source' => 'defect'],
        ],
    ])->assertRedirect();

    expect(SupplierReturn::count())->toBe(0)
        ->and($book->refresh()->inventoryStocks()->whereHas('warehouse', fn ($q) => $q->where('is_defect', 1))->sum('qty'))->toBe(1);
});

it('validates return reason and stock source', function (): void {
    $supplier = Supplier::factory()->create();
    $book = Book::factory()->withStock(malang: 0, defect: 2)->create(['aktif' => true]);

    $this->post(route('admin.supplier-returns.store'), [
        'supplier_id' => $supplier->id,
        'return_date' => '2026-08-11',
        'items' => [
            ['book_id' => $book->id, 'qty' => 1, 'price' => 10_000, 'reason' => '', 'source' => 'defect'],
        ],
    ])->assertSessionHasErrors('items.0.reason');

    $this->post(route('admin.supplier-returns.store'), [
        'supplier_id' => $supplier->id,
        'return_date' => '2026-08-11',
        'items' => [
            ['book_id' => $book->id, 'qty' => 1, 'price' => 10_000, 'reason' => 'Rusak', 'source' => 'gudang-lain'],
        ],
    ])->assertSessionHasErrors('items.0.source');
});

// ── Hutang supplier ───────────────────────────────────────────────

it('records a payment that reduces the outstanding balance', function (): void {
    $supplier = Supplier::factory()->create();
    $purchase = SupplierPurchase::factory()->create([
        'supplier_id' => $supplier->id,
        'total' => 400_000,
    ]);

    $this->post(route('admin.supplier-debts.payments.store'), [
        'supplier_id' => $supplier->id,
        'amount' => 150_000,
        'payment_date' => '2026-08-12',
        'purchase_id' => $purchase->id,
        'notes' => 'Pembayaran transfer',
    ])->assertRedirect();

    $payment = $supplier->payments()->first();
    expect($payment->amount)->toBe(150_000)
        ->and($payment->supplier_purchase_id)->toBe($purchase->id)
        ->and(app(SupplierService::class)->saldoHutang($supplier))->toBe(250_000);
});

it('tracks outstanding balance per invoice (sisa per faktur)', function (): void {
    $supplier = Supplier::factory()->create();
    $purchase = SupplierPurchase::factory()->create([
        'supplier_id' => $supplier->id,
        'total' => 300_000,
    ]);
    SupplierPayment::factory()->create([
        'supplier_id' => $supplier->id,
        'amount' => 100_000,
        'supplier_purchase_id' => $purchase->id,
    ]);
    SupplierReturn::factory()->create([
        'supplier_id' => $supplier->id,
        'supplier_purchase_id' => $purchase->id,
        'total' => 50_000,
    ]);

    expect(app(SupplierService::class)->sisaPerFaktur($purchase->refresh()))->toBe(150_000);
});

it('lists unpaid invoices in the debt page', function (): void {
    $supplier = Supplier::factory()->create(['nama' => 'Penerbit D']);
    $paid = SupplierPurchase::factory()->create([
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-LUNAS',
        'total' => 100_000,
    ]);
    SupplierPayment::factory()->create([
        'supplier_id' => $supplier->id,
        'amount' => 100_000,
        'supplier_purchase_id' => $paid->id,
    ]);
    $unpaid = SupplierPurchase::factory()->create([
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-HUTANG',
        'total' => 250_000,
    ]);

    $response = $this->get(route('admin.supplier-debts.index'));
    $response->assertOk();

    $props = inertiaProps($response);
    $invoiceRefs = collect($props['invoices'])->pluck('ref_code');

    expect($invoiceRefs)->toContain('PO-HUTANG')
        ->not->toContain('PO-LUNAS');

    $invoice = collect($props['invoices'])->firstWhere('ref_code', 'PO-HUTANG');
    expect($invoice['sisa'])->toBe(250_000);
});

it('validates payment requires a supplier', function (): void {
    $this->post(route('admin.supplier-debts.payments.store'), [
        'amount' => 50_000,
        'payment_date' => '2026-08-12',
    ])->assertSessionHasErrors('supplier_id');
});

// ── Delete supplier ───────────────────────────────────────────────

it('blocks deleting a supplier with transaction history', function (): void {
    $supplier = Supplier::factory()->create();
    SupplierPurchase::factory()->create(['supplier_id' => $supplier->id]);

    $this->delete(route('admin.suppliers.destroy', $supplier));

    expect(Supplier::find($supplier->id))->not->toBeNull();
});

it('soft deletes a supplier without transactions and restores it', function (): void {
    $supplier = Supplier::factory()->create();

    $this->delete(route('admin.suppliers.destroy', $supplier))->assertRedirect();

    expect(Supplier::find($supplier->id))->toBeNull();

    $this->post(route('admin.suppliers.restore', $supplier));

    expect(Supplier::find($supplier->id))->not->toBeNull();
});

// ── Laporan .xlsx ─────────────────────────────────────────────────

it('exports the supplier report as xlsx with date & supplier filter', function (): void {
    $supplierA = Supplier::factory()->create(['nama' => 'Penerbit Alpha']);
    $supplierB = Supplier::factory()->create(['nama' => 'Penerbit Beta']);
    $book = Book::factory()->create(['aktif' => true, 'judul' => 'Buku Laporan']);

    $purchaseA = SupplierPurchase::factory()->create([
        'supplier_id' => $supplierA->id,
        'ref_code' => 'PO-A-1',
        'purchase_date' => '2026-08-01',
        'total' => 100_000,
    ]);
    $purchaseA->items()->create(['book_id' => $book->id, 'qty' => 2, 'price' => 50_000, 'subtotal' => 100_000]);

    SupplierPurchase::factory()->create([
        'supplier_id' => $supplierB->id,
        'ref_code' => 'PO-B-1',
        'purchase_date' => '2026-08-02',
        'total' => 50_000,
    ]);
    SupplierPurchase::factory()->create([
        'supplier_id' => $supplierA->id,
        'ref_code' => 'PO-A-2',
        'purchase_date' => '2026-07-01',
        'total' => 999_000,
    ]);

    $response = $this->get(route('admin.supplier-reports.export', [
        'from' => '2026-08-01',
        'to' => '2026-08-31',
        'supplier_id' => $supplierA->id,
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');
    expect($response->headers->get('content-disposition'))->toContain('laporan-supplier');

    // Isi file: magic bytes ZIP (xlsx) + memuat baris supplier A periode Agustus.
    $content = $response->streamedContent();
    expect(substr($content, 0, 2))->toBe('PK');

    $tmp = tempnam(sys_get_temp_dir(), 'supplier_export_').'.xlsx';
    file_put_contents($tmp, $content);

    $reader = IOFactory::createReaderForFile($tmp);
    $sheet = $reader->load($tmp)->getActiveSheet();

    expect($sheet->getCell('A5')->getValue())->toBe('01/08/2026')
        ->and($sheet->getCell('D5')->getValue())->toBe('Penerbit Alpha')
        ->and($sheet->getCell('H6')->getValue())->toBe('Total Barang Masuk')
        ->and($sheet->getCell('I6')->getValue())->toBe(100_000);

    unlink($tmp);
});

it('exports report for all suppliers when no supplier filter given', function (): void {
    $supplierA = Supplier::factory()->create(['nama' => 'Penerbit Alpha']);
    $supplierB = Supplier::factory()->create(['nama' => 'Penerbit Beta']);
    $book = Book::factory()->create(['aktif' => true, 'judul' => 'Buku Laporan']);

    $purchaseA = SupplierPurchase::factory()->create([
        'supplier_id' => $supplierA->id,
        'ref_code' => 'PO-A-1',
        'purchase_date' => '2026-08-01',
        'total' => 100_000,
    ]);
    $purchaseA->items()->create(['book_id' => $book->id, 'qty' => 2, 'price' => 50_000, 'subtotal' => 100_000]);

    $purchaseB = SupplierPurchase::factory()->create([
        'supplier_id' => $supplierB->id,
        'ref_code' => 'PO-B-1',
        'purchase_date' => '2026-08-02',
        'total' => 50_000,
    ]);
    $purchaseB->items()->create(['book_id' => $book->id, 'qty' => 1, 'price' => 50_000, 'subtotal' => 50_000]);

    $response = $this->get(route('admin.supplier-reports.export', [
        'from' => '2026-08-01',
        'to' => '2026-08-31',
    ]));

    $content = $response->streamedContent();
    $tmp = tempnam(sys_get_temp_dir(), 'supplier_export_').'.xlsx';
    file_put_contents($tmp, $content);

    $sheet = IOFactory::createReaderForFile($tmp)->load($tmp)->getActiveSheet();

    expect($sheet->getCell('D5')->getValue())->toBe('Penerbit Alpha')
        ->and($sheet->getCell('D6')->getValue())->toBe('Penerbit Beta')
        ->and($sheet->getCell('H7')->getValue())->toBe('Total Barang Masuk')
        ->and($sheet->getCell('I7')->getValue())->toBe(150_000);

    unlink($tmp);
});

it('exports only returns when jenis filter is retur', function (): void {
    $supplier = Supplier::factory()->create(['nama' => 'Penerbit E']);
    $book = Book::factory()->create(['aktif' => true, 'judul' => 'Buku Retur Laporan']);

    $purchase = SupplierPurchase::factory()->create([
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-R-1',
        'purchase_date' => '2026-08-01',
        'total' => 200_000,
    ]);
    $purchase->items()->create(['book_id' => $book->id, 'qty' => 4, 'price' => 50_000, 'subtotal' => 200_000]);

    $return = SupplierReturn::factory()->create([
        'supplier_id' => $supplier->id,
        'return_date' => '2026-08-05',
        'total' => 50_000,
    ]);
    $return->items()->create([
        'book_id' => $book->id,
        'qty' => 1,
        'price' => 50_000,
        'reason' => 'Halaman rusak',
        'subtotal' => 50_000,
    ]);

    $response = $this->get(route('admin.supplier-reports.export', [
        'from' => '2026-08-01',
        'to' => '2026-08-31',
        'jenis' => 'retur',
    ]));

    $content = $response->streamedContent();
    $tmp = tempnam(sys_get_temp_dir(), 'supplier_export_').'.xlsx';
    file_put_contents($tmp, $content);

    $sheet = IOFactory::createReaderForFile($tmp)->load($tmp)->getActiveSheet();

    // Hanya 1 baris data: retur (bukan pembelian), dengan alasan di kolom I.
    expect($sheet->getCell('A5')->getValue())->toBe('05/08/2026')
        ->and($sheet->getCell('B5')->getValue())->toBe('Retur')
        ->and($sheet->getCell('I5')->getValue())->toBe('Halaman rusak')
        ->and($sheet->getCell('I7')->getValue())->toBe(50_000);

    unlink($tmp);
});

// ── Integritas faktur & saldo (guard) ─────────────────────────────

it('rejects a return linked to another supplier invoice', function (): void {
    $supplierA = Supplier::factory()->create(['nama' => 'Penerbit A']);
    $supplierB = Supplier::factory()->create(['nama' => 'Penerbit B']);
    $purchaseB = SupplierPurchase::factory()->create([
        'supplier_id' => $supplierB->id,
        'total' => 100_000,
    ]);
    $book = Book::factory()->withStock(malang: 5, defect: 3)->create();

    $this->post(route('admin.supplier-returns.store'), [
        'supplier_id' => $supplierA->id,
        'return_date' => '2026-08-11',
        'purchase_id' => $purchaseB->id,
        'items' => [
            ['book_id' => $book->id, 'qty' => 1, 'price' => 10_000, 'reason' => 'Rusak', 'source' => 'defect'],
        ],
    ])->assertSessionHasErrors('purchase_id');

    expect(SupplierReturn::count())->toBe(0);
});

it('rejects a return with qty exceeding the purchased qty', function (): void {
    $supplier = Supplier::factory()->create(['nama' => 'Penerbit E']);
    $book = Book::factory()->withStock(malang: 5, defect: 5)->create(['judul' => 'Buku Cap']);
    $purchase = SupplierPurchase::factory()->create([
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-CAP',
        'total' => 50_000,
    ]);
    $purchase->items()->create(['book_id' => $book->id, 'qty' => 2, 'price' => 25_000, 'subtotal' => 50_000]);

    $this->post(route('admin.supplier-returns.store'), [
        'supplier_id' => $supplier->id,
        'return_date' => '2026-08-11',
        'purchase_id' => $purchase->id,
        'items' => [
            ['book_id' => $book->id, 'qty' => 3, 'price' => 25_000, 'reason' => 'Rusak', 'source' => 'defect'],
        ],
    ])->assertRedirect();

    expect(SupplierReturn::count())->toBe(0);
});

it('rejects a return with price above the purchase price', function (): void {
    $supplier = Supplier::factory()->create(['nama' => 'Penerbit F']);
    $book = Book::factory()->withStock(malang: 5, defect: 5)->create();
    $purchase = SupplierPurchase::factory()->create([
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-HARGA',
        'total' => 50_000,
    ]);
    $purchase->items()->create(['book_id' => $book->id, 'qty' => 2, 'price' => 25_000, 'subtotal' => 50_000]);

    $this->post(route('admin.supplier-returns.store'), [
        'supplier_id' => $supplier->id,
        'return_date' => '2026-08-11',
        'purchase_id' => $purchase->id,
        'items' => [
            ['book_id' => $book->id, 'qty' => 1, 'price' => 40_000, 'reason' => 'Rusak', 'source' => 'defect'],
        ],
    ])->assertRedirect();

    expect(SupplierReturn::count())->toBe(0);
});

it('rejects a return total exceeding the remaining invoice value', function (): void {
    $supplier = Supplier::factory()->create(['nama' => 'Penerbit G']);
    $book = Book::factory()->withStock(malang: 10, defect: 10)->create();
    $purchase = SupplierPurchase::factory()->create([
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-SISA',
        'total' => 100_000,
    ]);
    $purchase->items()->create(['book_id' => $book->id, 'qty' => 10, 'price' => 10_000, 'subtotal' => 100_000]);

    // Retur pertama 40.000 — valid.
    $this->post(route('admin.supplier-returns.store'), [
        'supplier_id' => $supplier->id,
        'return_date' => '2026-08-11',
        'purchase_id' => $purchase->id,
        'items' => [
            ['book_id' => $book->id, 'qty' => 4, 'price' => 10_000, 'reason' => 'Rusak', 'source' => 'defect'],
        ],
    ])->assertRedirect();

    // Retur kedua 70.000 → total 110.000 > sisa 60.000 — ditolak.
    $this->post(route('admin.supplier-returns.store'), [
        'supplier_id' => $supplier->id,
        'return_date' => '2026-08-12',
        'purchase_id' => $purchase->id,
        'items' => [
            ['book_id' => $book->id, 'qty' => 7, 'price' => 10_000, 'reason' => 'Rusak', 'source' => 'defect'],
        ],
    ])->assertRedirect();

    expect(SupplierReturn::count())->toBe(1);
});

it('rejects a payment linked to another supplier invoice', function (): void {
    $supplierA = Supplier::factory()->create(['nama' => 'Penerbit H']);
    $supplierB = Supplier::factory()->create(['nama' => 'Penerbit I']);
    $purchaseB = SupplierPurchase::factory()->create([
        'supplier_id' => $supplierB->id,
        'total' => 100_000,
    ]);

    $this->post(route('admin.supplier-debts.payments.store'), [
        'supplier_id' => $supplierA->id,
        'amount' => 50_000,
        'payment_date' => '2026-08-12',
        'purchase_id' => $purchaseB->id,
    ])->assertSessionHasErrors('purchase_id');

    expect(SupplierPayment::count())->toBe(0);
});

it('rejects a payment exceeding the remaining invoice', function (): void {
    $supplier = Supplier::factory()->create(['nama' => 'Penerbit J']);
    $purchase = SupplierPurchase::factory()->create([
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-BAYAR',
        'total' => 100_000,
    ]);

    $this->post(route('admin.supplier-debts.payments.store'), [
        'supplier_id' => $supplier->id,
        'amount' => 150_000,
        'payment_date' => '2026-08-12',
        'purchase_id' => $purchase->id,
    ])->assertRedirect();

    expect(SupplierPayment::count())->toBe(0)
        ->and(app(SupplierService::class)->saldoHutang($supplier))->toBe(100_000);
});

it('logs supplier payments in the activity log', function (): void {
    $supplier = Supplier::factory()->create(['nama' => 'Penerbit K']);
    $purchase = SupplierPurchase::factory()->create([
        'supplier_id' => $supplier->id,
        'total' => 400_000,
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.supplier-debts.payments.store'), [
            'supplier_id' => $supplier->id,
            'amount' => 150_000,
            'payment_date' => '2026-08-12',
            'purchase_id' => $purchase->id,
        ])->assertRedirect();

    $log = ActivityLog::where('action', 'supplier.payment')->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toContain('Penerbit K')
        ->and($log->description)->toContain('150.000');
});

// ── Pilihan gudang pembelian & retur otomatis ─────────────────────

it('records a purchase into the selected warehouse', function (): void {
    $supplier = Supplier::factory()->create(['nama' => 'Penerbit Gudang']);
    $warehouse = Warehouse::factory()->create(['kode' => 'surabaya', 'nama' => 'Surabaya', 'is_defect' => false]);
    $book = Book::factory()->withStock(malang: 0)->create();

    $this->post(route('admin.purchases.store'), [
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-GUDANG',
        'purchase_date' => '2026-08-10',
        'warehouse_kode' => 'surabaya',
        'items' => [['book_id' => $book->id, 'qty' => 3, 'price' => 20_000]],
    ])->assertRedirect();

    $purchase = $supplier->purchases()->first();
    expect($purchase->warehouse_kode)->toBe('surabaya');

    $movement = InventoryMovement::where('book_id', $book->id)->latest()->first();
    expect($movement->type->value)->toBe('in')
        ->and($movement->toWarehouse->kode)->toBe('surabaya');

    // Stok Surabaya bertambah, Malang tetap.
    expect(app(InventoryService::class)->availableStock($book->refresh(), $warehouse))->toBe(3);
});

it('rejects a purchase into the defect warehouse', function (): void {
    $supplier = Supplier::factory()->create();
    $book = Book::factory()->create();

    $this->post(route('admin.purchases.store'), [
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-DEFECT',
        'purchase_date' => '2026-08-10',
        'warehouse_kode' => 'defect',
        'items' => [['book_id' => $book->id, 'qty' => 1, 'price' => 10_000]],
    ])->assertSessionHasErrors('warehouse_kode');

    expect(SupplierPurchase::count())->toBe(0);
});

it('requires a warehouse for purchases', function (): void {
    $supplier = Supplier::factory()->create();
    $book = Book::factory()->create();

    $this->post(route('admin.purchases.store'), [
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-NO-WH',
        'purchase_date' => '2026-08-10',
        'items' => [['book_id' => $book->id, 'qty' => 1, 'price' => 10_000]],
    ])->assertSessionHasErrors('warehouse_kode');
});

it('returns from the linked purchase warehouse automatically', function (): void {
    $supplier = Supplier::factory()->create(['nama' => 'Penerbit Retur Gudang']);
    $warehouse = Warehouse::factory()->create(['kode' => 'surabaya', 'nama' => 'Surabaya', 'is_defect' => false]);
    $book = Book::factory()->create();
    $purchase = SupplierPurchase::factory()->create([
        'supplier_id' => $supplier->id,
        'ref_code' => 'PO-RETUR-WH',
        'total' => 60_000,
        'warehouse_kode' => 'surabaya',
    ]);
    $purchase->items()->create(['book_id' => $book->id, 'qty' => 3, 'price' => 20_000, 'subtotal' => 60_000]);
    // Stok di gudang Surabaya.
    app(InventoryService::class)->move(
        book: $book,
        type: MovementType::In,
        qty: 3,
        to: $warehouse,
        reference: 'PO-RETUR-WH',
    );

    $this->post(route('admin.supplier-returns.store'), [
        'supplier_id' => $supplier->id,
        'return_date' => '2026-08-11',
        'purchase_id' => $purchase->id,
        'items' => [
            ['book_id' => $book->id, 'qty' => 1, 'price' => 20_000, 'reason' => 'Salah kirim', 'source' => 'normal'],
        ],
    ])->assertRedirect(route('admin.supplier-returns.index'));

    // Stok keluar dari gudang SURABAYA (bukan default).
    $movement = InventoryMovement::where('book_id', $book->id)->latest()->first();
    expect($movement->type->value)->toBe('out')
        ->and($movement->fromWarehouse->kode)->toBe('surabaya')
        ->and($movement->notes)->toContain('Salah kirim');
});
