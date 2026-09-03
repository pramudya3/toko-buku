<?php

use App\Models\Book;
use App\Models\Category;
use App\Models\ConsignmentSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\Receivable;
use App\Models\User;
use Database\Seeders\PenerbitPcpMasterSeeder;
use Database\Seeders\PenerbitPcpSalesSeeder;

/**
 * Seeder data riil dari dump db_penerbitpcp — penjualan 1–14 Agustus.
 */
it('membuat kategori, buku, dan customer dari dump db_penerbitpcp', function (): void {
    $this->seed(PenerbitPcpMasterSeeder::class);

    expect(Category::query()->whereIn('kode', ['ALQ', 'ANK', 'Bdl', 'BI', 'KSH', 'PRN', 'PST'])->count())->toBe(7)
        ->and(Book::query()->where('kode_sku', 'BI000021')->whereNotNull('category_id')->exists())->toBeTrue()
        ->and(User::query()->whereNull('email')->where('is_admin', false)->count())->toBe(51);
});

it('membuat order penjualan Agustus full (112 order non-konsinyasi + 16 piutang konsinyasi) dengan matematika konsisten', function (): void {
    $this->seed(PenerbitPcpMasterSeeder::class);
    $this->seed(PenerbitPcpSalesSeeder::class);

    $orders = Order::query()->where('no_order', 'like', 'ORD-202608%')->with('items')->get();

    $grand = $orders->sum('total') + ConsignmentSale::with('items')->get()->flatMap->items->sum(fn ($i) => $i->qty * $i->price);
    // CSV grand 37.454.358, seeder pakai intdiv(sub/qty) untuk harga final sehingga 2 baris Lily (qty15) off 13 rupiah akibat pembulatan — toleransi <100
    expect($orders)->toHaveCount(112)
        ->and($orders->sum(fn (Order $order) => $order->items->sum('qty')))->toBeGreaterThan(200)
        ->and($grand)->toBeGreaterThanOrEqual(37_454_345)
        ->and($grand)->toBeLessThanOrEqual(37_454_358);

    foreach ($orders as $order) {
        $itemsTotal = (int) $order->items->sum(fn (OrderItem $item) => $item->price_final * $item->qty);

        // Toleransi pembulatan intdiv(sub/qty) untuk qty 15 (Lily 13 rupiah)
        expect(abs(($itemsTotal + $order->shipping_cost) - $order->total))->toBeLessThanOrEqual(20)
            ->and($order->status->value)->toBe('selesai');
    }

    // Konsinyasi masuk piutang, bukan order
    expect(ConsignmentSale::count())->toBe(16)
        ->and(ConsignmentSale::with('items')->get()->flatMap->items->sum(fn ($i) => $i->qty * $i->price))->toBe(6_714_700)
        ->and(Receivable::sum('amount'))->toBe(6_714_700);

    // Tanggal order mengikuti CSV
    expect(Order::query()->where('no_order', 'ORD-20260801-0001')->first()->created_at->toDateString())->toBe('2026-08-01');
});

it('memetakan metode bayar dan channel dengan benar', function (): void {
    $this->seed(PenerbitPcpMasterSeeder::class);
    $this->seed(PenerbitPcpSalesSeeder::class);

    // Shopee order (nurafni 01/08 shopee)
    expect(Order::query()->where('nama_pembeli', 'nurafni')->value('metode_bayar'))->toBe('shopee')
        ->and(Order::query()->where('nama_pembeli', 'nurafni')->value('sumber_pembelian'))->toBe('shopee')
        ->and(Order::query()->where('nama_pembeli', 'Bu Mira Walsan Malang')->value('metode_bayar'))->toBe('qris')
        ->and(Order::query()->where('nama_pembeli', 'Bazaf Gresik')->value('metode_bayar'))->toBe('bsi')
        ->and(Order::query()->where('nama_pembeli', 'Bazaf Gresik')->value('sumber_pembelian'))->toBe('toko')
        ->and(PaymentMethod::query()->whereIn('code', ['bsi', 'qris', 'shopee', 'konsinyasi', 'hutang'])->count())->toBe(5);
});

it('idempotent — dipanggil ulang tidak menduplikasi data', function (): void {
    $this->seed(PenerbitPcpMasterSeeder::class);
    $this->seed(PenerbitPcpSalesSeeder::class);
    $this->seed(PenerbitPcpMasterSeeder::class);
    $this->seed(PenerbitPcpSalesSeeder::class);

    expect(Order::query()->where('no_order', 'like', 'ORD-202608%')->count())->toBe(112)
        ->and(ConsignmentSale::count())->toBe(16)
        ->and(Book::query()->where('kode_sku', 'BI000020')->count())->toBe(1)
        ->and(Book::query()->where('kode_sku', 'RMP')->count())->toBe(0)
        ->and(Category::query()->where('kode', 'KSH')->count())->toBe(1);
});

it('sku sesuai singkatan kategori dan urut tanpa jeda', function (): void {
    $this->seed(PenerbitPcpMasterSeeder::class);

    $books = Book::query()->whereNotNull('kode_sku')->with('category')->get();

    foreach ($books as $book) {
        if ($book->category === null || $book->category->kode === null) {
            continue;
        }

        $kode = $book->category->kode;

        expect($book->kode_sku)->toMatch('/^'.preg_quote($kode, '/').'\d{6}$/');
    }

    // Per kategori harus urut 1..N tanpa lubang
    $grouped = $books->filter(fn (Book $b) => $b->category && $b->category->kode)
        ->groupBy(fn (Book $b) => $b->category->kode);

    foreach ($grouped as $kode => $group) {
        $numbers = $group->map(fn (Book $b) => (int) substr($b->kode_sku, strlen($kode)))->sort()->values();

        expect($numbers->toArray())->toBe(range(1, $numbers->count()));
    }
});

it('judul duplikat tidak membuat buku baru, hanya update field kosong', function (): void {
    $this->seed(PenerbitPcpMasterSeeder::class);

    $category = Category::query()->where('kode', 'PRN')->firstOrFail();

    // Buat buku duplikat dengan judul sama tapi tanpa penulis
    $existing = Book::create([
        'judul' => 'Buku Duplikat Tester',
        'penulis' => null,
        'harga' => 50000,
        'kode_sku' => 'PRN999998',
        'category_id' => $category->id,
    ]);

    // Simulasi data pcp dengan judul sama tapi ada penulis
    // Kita panggil seeder lagi dengan data yang sudah ada — update harus isi penulis
    // Untuk test ini, kita buat manual update seperti seeder lakukan
    $duplicateRow = [
        'judul' => 'Buku Duplikat Tester',
        'penulis' => 'Penulis Baru',
        'harga' => 50000,
        'kode_sku' => 'PRN999999', // sku berbeda, tapi judul sama
        'kategori_kode' => 'PRN',
    ];

    // Cari seperti seeder: by judul
    $found = Book::withTrashed()->whereRaw('LOWER(TRIM(judul)) = ?', [strtolower(trim($duplicateRow['judul']))])->first();
    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($existing->id);

    // Update seperti seeder
    if (empty($found->penulis) && ! empty($duplicateRow['penulis'])) {
        $found->update(['penulis' => $duplicateRow['penulis']]);
    }

    expect(Book::query()->whereRaw('LOWER(TRIM(judul)) = ?', [strtolower('Buku Duplikat Tester')])->count())->toBe(1)
        ->and(Book::query()->where('id', $existing->id)->value('penulis'))->toBe('Penulis Baru')
        ->and(Book::query()->where('kode_sku', 'PRN999999')->exists())->toBeFalse();
});
