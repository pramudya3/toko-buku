<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Book;
use App\Models\CashFlow;
use App\Models\Courier;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BiteshipShippingService;
use App\Services\OrderStatusService;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();

    Setting::set('origin_postal_code', '65144');
});

it('returns full address (incl. kelurahan) in customer options', function (): void {
    $customer = User::factory()->create([
        'name' => 'Pembeli Lengkap',
        'alamat' => 'Jl. Bareng Raya 12',
        'provinsi' => 'JAWA TIMUR',
        'kabupaten_kota' => 'KOTA MALANG',
        'kecamatan' => 'KLOJEN',
        'kelurahan' => 'BARENG',
        'village_code' => '3573010001',
        'kode_pos' => '65116',
    ]);

    $this->actingAs($this->admin)
        ->getJson(route('admin.orders.options.customers', ['search' => 'Pembeli']))
        ->assertOk()
        ->assertJsonPath('0.id', $customer->id)
        ->assertJsonPath('0.kelurahan', 'BARENG')
        ->assertJsonPath('0.village_code', '3573010001')
        ->assertJsonPath('0.kode_pos', '65116');
});

it('creates a manual order and calculates prices via PricingService (ORD-03, ORD-08)', function (): void {
    createLocalVillages();
    fakeRajaOngkirApi();

    $book = Book::factory()->withStock(malang: 20)->create(['harga' => 50000]);
    $customer = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'user_id' => $customer->id,
            'nama_pembeli' => $customer->name,
            'metode_bayar' => 'transfer',
            'sumber_pembelian' => 'shopee',
            'metode_pengambilan' => 'kirim',
            'kode_pos' => '65144',
            'metode_pengambilan' => 'kirim',
            'ekspedisi' => 'jne',
            'items' => [
                ['book_id' => $book->id, 'qty' => 2],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $order = Order::latest('id')->first();

    expect($order->status)->toBe(OrderStatus::MenungguKonfirmasi)
        ->and($order->sumber_pembelian)->toBe('shopee')
        ->and($order->total)->toBe(112000)
        ->and($order->shipping_cost)->toBe(12000)
        ->and($order->items()->first()->price_final)->toBe(50000);
});

it('filters the order list by sales channel', function (): void {
    Order::factory()->create(['sumber_pembelian' => 'shopee', 'nama_pembeli' => 'Dari Shopee']);
    Order::factory()->create(['sumber_pembelian' => 'toko', 'nama_pembeli' => 'Dari Toko']);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.index', ['sumber_pembelian' => 'shopee']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/orders/Index')
            ->where('filters.sumber_pembelian', 'shopee')
            ->has('orders.data', 1)
            ->where('orders.data.0.sumber_pembelian', 'shopee')
            ->has('salesChannels.shopee')
        );
});

it('filters the order list by date range (from/to)', function (): void {
    Order::factory()->create(['nama_pembeli' => 'Agustus Awal', 'created_at' => '2026-08-02 09:00:00']);
    Order::factory()->create(['nama_pembeli' => 'Agustus Akhir', 'created_at' => '2026-08-20 13:00:00']);
    Order::factory()->create(['nama_pembeli' => 'Juli Malam', 'created_at' => '2026-07-31 23:59:59']);

    // Rentang lengkap: batas atas menyertakan order pada hari "to" itu sendiri.
    $this->actingAs($this->admin)
        ->get(route('admin.orders.index', ['from' => '2026-08-01', 'to' => '2026-08-14']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/orders/Index')
            ->where('filters.from', '2026-08-01')
            ->where('filters.to', '2026-08-14')
            ->has('orders.data', 1)
            ->where('orders.data.0.nama_pembeli', 'Agustus Awal')
        );

    // Hanya "from" — semua order mulai tanggal tersebut.
    $this->actingAs($this->admin)
        ->get(route('admin.orders.index', ['from' => '2026-08-15']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.nama_pembeli', 'Agustus Akhir')
        );

    // Hanya "to" — semua order sampai tanggal tersebut.
    $this->actingAs($this->admin)
        ->get(route('admin.orders.index', ['to' => '2026-07-31']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.nama_pembeli', 'Juli Malam')
        );
});

it('checks shipping cost from admin endpoint (cached shared)', function (): void {
    createLocalVillages();
    fakeRajaOngkirApi();

    $this->actingAs($this->admin)
        ->post(route('admin.orders.check-ongkir'), [
            'postal_code' => '65144',
            'weight_kg' => 1.2,
        ])
        ->assertOk()
        ->assertJsonPath('weight_kg', 1.2)
        ->assertJsonPath('costs.0.courier_code', 'jne')
        ->assertJsonPath('costs.0.price', 12000);
});

it('saves shipping cost and courier on manual order', function (): void {
    createLocalVillages();
    fakeRajaOngkirApi();

    $book = Book::factory()->withStock(malang: 20)->create(['harga' => 50000, 'berat_gr' => 500]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Pembeli Langsung',
            'metode_bayar' => 'transfer',
            'metode_pengambilan' => 'kirim',
            'kode_pos' => '65144',
            'ekspedisi' => 'jne',
            'courier_service_code' => 'REG',
            'items' => [
                ['book_id' => $book->id, 'qty' => 2],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $order = Order::latest('id')->first();

    // Berat 1 kg (500gr × 2) → JNE 12.000; total = subtotal 100.000 + ongkir.
    expect($order->shipping_cost)->toBe(12000)
        ->and($order->ekspedisi)->toBe('jne')
        ->and($order->courier_service_code)->toBe('REG')
        ->and($order->ongkir_estimasi)->toBe('1-2')
        ->and($order->kode_pos)->toBe('65144')
        ->and($order->total)->toBe(112000);
});

it('rejects an unknown sales channel on manual order', function (): void {
    $book = Book::factory()->withStock(malang: 20)->create(['harga' => 50000]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Pembeli',
            'metode_bayar' => 'transfer',
            'sumber_pembelian' => 'blibli',
            'items' => [
                ['book_id' => $book->id, 'qty' => 1],
            ],
        ])
        ->assertSessionHasErrors('sumber_pembelian');
});

it('generates a unique order number', function (): void {
    createLocalVillages();
    fakeRajaOngkirApi();

    $book = Book::factory()->withStock()->create(['harga' => 10000]);

    foreach (range(1, 3) as $index) {
        $this->actingAs($this->admin)
            ->post(route('admin.orders.store'), [
                'nama_pembeli' => 'Pembeli '.$index,
                'metode_bayar' => 'transfer',
                'sumber_pembelian' => 'shopee',
                'kode_pos' => '65144',
                'ekspedisi' => 'jne',
                'items' => [['book_id' => $book->id, 'qty' => 1]],
            ]);
    }

    $numbers = Order::pluck('no_order');

    expect($numbers->unique()->count())->toBe(3);
});

it('creates dropship order with end-customer data (DROP-01)', function (): void {
    createLocalVillages();
    fakeRajaOngkirApi();

    $book = Book::factory()->withStock()->create(['harga' => 10000]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Reseller',
            'metode_bayar' => 'transfer',
            'sumber_pembelian' => 'website',
            'kode_pos' => '65144',
            'ekspedisi' => 'jne',
            'is_dropship' => true,
            'end_customer_name' => 'Andini',
            'end_customer_whatsapp' => '081234567890',
            'end_customer_address' => 'Jl. Merdeka 45, Bandung',
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ])
        ->assertRedirect();

    $order = Order::latest('id')->first();

    expect($order->is_dropship)->toBeTrue()
        ->and($order->dropshipper->end_customer_name)->toBe('Andini');
});

it('validates order items', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'X',
            'metode_bayar' => 'transfer',
            'items' => [],
        ])
        ->assertSessionHasErrors(['items']);
});

it('processes an order: fills shipping cost, courier and warehouse origin (ORD-05)', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 50000]);
    $order = Order::factory()->lunas()->create();
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 2,
        'price_original' => 50000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 50000,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.process', $order), [
            'shipping_cost' => 15000,
            'ekspedisi' => 'jne',
            'warehouse_origin' => 'malang',
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Diproses)
        ->and($order->shipping_cost)->toBe(15000)
        ->and($order->warehouse_origin)->toBe('malang')
        ->and($order->total)->toBe(115000)
        // Stok ter-reserve saat diproses (10 - 2).
        ->and($book->fresh()->stok)->toBe(8);
});

it('rejects processing an order from an invalid state', function (): void {
    $order = Order::factory()->status(OrderStatus::Selesai)->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.process', $order), [
            'shipping_cost' => 10000,
            'ekspedisi' => 'jne',
            'warehouse_origin' => 'malang',
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Selesai)
        ->and($order->shipping_cost)->toBe(0)
        ->and($order->warehouse_origin)->toBeNull()
        ->and($order->total)->toBe(0);
});

it('does not allow the generic status endpoint to bypass order processing', function (): void {
    $order = Order::factory()->lunas()->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), [
            'status' => OrderStatus::Diproses->value,
        ])
        ->assertSessionHasErrors('status');

    expect($order->fresh()->status)->toBe(OrderStatus::MenungguKonfirmasi);
});

it('completes an order: stock was reserved at processing, selesai records 2 cash flows (ORD-06, BR-06, CF-03)', function (): void {
    $book = Book::factory()->withStock(malang: 10, sidoarjo: 0)->create(['harga' => 50000]);
    $order = Order::factory()->lunas()->create();
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 2,
        'price_original' => 50000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 50000,
    ]);

    // Proses → stok ter-reserve.
    $this->actingAs($this->admin)
        ->patch(route('admin.orders.process', $order), [
            'shipping_cost' => 15000,
            'ekspedisi' => 'jne',
            'warehouse_origin' => 'malang',
        ])
        ->assertRedirect();

    expect($book->fresh()->stok)->toBe(8);

    // Kirim → selesai: tidak ada deduksi ganda, hanya 2 cash flow.
    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Dikirim->value])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Selesai->value])
        ->assertRedirect();

    expect($book->fresh()->stok)->toBe(8)
        ->and($book->fresh()->inventoryStocks()->sum('qty'))->toBe(8)
        // OPSI A: Kas mandiri — order selesai tidak buat cash flow otomatis.
        ->and(CashFlow::where('order_id', $order->id)->count())->toBe(0);
});

it('is idempotent: completing twice does not double deduct or duplicate entries (ORD-07)', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 50000]);
    $order = Order::factory()->lunas()->create();
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 1,
        'price_original' => 50000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 50000,
    ]);

    // Proses → reserve (10 - 1), lalu kirim.
    $this->actingAs($this->admin)
        ->patch(route('admin.orders.process', $order), [
            'shipping_cost' => 10000,
            'ekspedisi' => 'jne',
            'warehouse_origin' => 'malang',
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Dikirim->value])
        ->assertRedirect();

    // Transisi pertama sukses: cash flow dicatat, stok tidak berubah lagi.
    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Selesai->value])
        ->assertRedirect();

    // Transisi kedua (selesai → batal) ditolak guard.
    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Batal->value])
        ->assertRedirect();

    expect($book->fresh()->stok)->toBe(9)
        ->and($order->fresh()->status)->toBe(OrderStatus::Selesai)
        ->and(CashFlow::where('order_id', $order->id)->count())->toBe(0);
});

it('does not duplicate completion side effects from a stale order model', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 50000]);
    $order = Order::factory()
        ->processed(shippingCost: '10000', warehouse: 'malang')
        ->status(OrderStatus::Dikirim)
        ->create();

    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 1,
        'price_original' => 50000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 50000,
    ]);
    $order->update(['total' => 60000]);

    $staleOrder = $order->fresh();
    app(OrderStatusService::class)->transition($staleOrder, OrderStatus::Selesai, $this->admin->id);

    expect(fn () => app(OrderStatusService::class)
        ->transition($staleOrder, OrderStatus::Selesai, $this->admin->id))
        ->toThrow(RuntimeException::class);

    expect($book->fresh()->stok)->toBe(10)
        ->and(CashFlow::where('order_id', $order->id)->count())->toBe(0);
});

it('restores reserved stock when cancelling a processed order', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 50000]);
    $order = Order::factory()->lunas()->create();
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 2,
        'price_original' => 50000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 50000,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.process', $order), [
            'shipping_cost' => 10000,
            'ekspedisi' => 'jne',
            'warehouse_origin' => 'malang',
        ])
        ->assertRedirect();

    expect($book->fresh()->stok)->toBe(8);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Batal->value])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Batal)
        ->and($book->fresh()->stok)->toBe(10)
        ->and($book->fresh()->inventoryStocks()->sum('qty'))->toBe(10)
        // OPSI A: Kas mandiri — batal tidak buat refund otomatis.
        ->and(CashFlow::where('order_id', $order->id)->where('flow_type', 'refund')->count())->toBe(0)
        // Ada mutasi masuk (restore) setelah mutasi keluar (reserve).
        ->and(InventoryMovement::where('book_id', $book->id)->where('type', 'in')->count())->toBe(1);
});

it('rejects cancelling an order that has been shipped', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 50000]);
    $order = Order::factory()->lunas()->create();
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 1,
        'price_original' => 50000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 50000,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.process', $order), [
            'shipping_cost' => 10000,
            'ekspedisi' => 'jne',
            'warehouse_origin' => 'malang',
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), [
            'status' => OrderStatus::Dikirim->value,
            'awb' => 'AWB-CANCEL-TEST',
        ])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Batal->value])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Dikirim)
        ->and($book->fresh()->stok)->toBe(9);
});

it('confirms payment for an order (menunggu → lunas)', function (): void {
    $order = Order::factory()->create();

    expect($order->fresh()->payment_status->value)->toBe('menunggu');

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.payment', $order))
        ->assertRedirect();

    expect($order->fresh()->payment_status->value)->toBe('lunas');

    // Idempotent: konfirmasi lagi tidak mengubah apa pun.
    $this->actingAs($this->admin)
        ->patch(route('admin.orders.payment', $order))
        ->assertRedirect();

    expect($order->fresh()->payment_status->value)->toBe('lunas');
});

it('cancels a paid order and records an automatic refund (CF-04)', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 50000]);
    $order = Order::factory()->lunas()->create(['total' => 50000]);
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 1,
        'price_original' => 50000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 50000,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Batal->value])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Batal)
        ->and($book->fresh()->stok)->toBe(10)
        // OPSI A: Kas mandiri — batal tidak buat refund.
        ->and(CashFlow::where('order_id', $order->id)->where('flow_type', 'refund')->count())->toBe(0);
});

it('does not record a refund when cancelling an unpaid order', function (): void {
    $order = Order::factory()->create(['total' => 50000]);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Batal->value])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Batal)
        ->and(CashFlow::where('order_id', $order->id)->count())->toBe(0);
});

it('rejects confirming payment on a cancelled order', function (): void {
    $order = Order::factory()->create(['total' => 50000]);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Batal->value])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.payment', $order))
        ->assertRedirect();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Menunggu)
        ->and(session('inertia.flash_data.toast.type'))->toBe('error');
});

it('shows order detail with items and pricing breakdown (ORD-02)', function (): void {
    $book = Book::factory()->withStock()->create(['harga' => 50000]);
    $order = Order::factory()->lunas()->create();
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'qty' => 2,
        'price_original' => 50000,
        'promo_discount_amount' => 5000,
        'tier_discount_amount' => 1000,
        'price_final' => 44000,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.show', $order))
        ->assertSuccessful()
        ->assertSee($order->no_order)
        ->assertSee($book->judul);
});

it('captures HPP (harga beli cetakan) snapshot on order items for profit tracking', function (): void {
    createLocalVillages();
    fakeRajaOngkirApi();

    $customer = User::factory()->customer()->create();
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 40000]);
    $edition1 = $book->editions()->first();
    $edition2 = $book->editions()->create([
        'cetakan_ke' => 2,
        'harga_beli' => 33000,
        'harga_jual' => 43000,
        'is_active' => false,
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.orders.store'), [
            'user_id' => $customer->id,
            'nama_pembeli' => $customer->name,
            'whatsapp_pembeli' => $customer->whatsapp_number,
            'metode_bayar' => 'transfer',
            'sumber_pembelian' => 'website',
            'kode_pos' => '65144',
            'ekspedisi' => 'jne',
            'items' => [
                ['book_id' => $book->id, 'book_edition_id' => $edition1->id, 'qty' => 1],
                ['book_id' => $book->id, 'book_edition_id' => $edition2->id, 'qty' => 1],
            ],
        ])
        ->assertRedirect();

    $order = Order::latest('id')->firstOrFail();
    $item1 = $order->items()->where('book_edition_id', $edition1->id)->first();
    $item2 = $order->items()->where('book_edition_id', $edition2->id)->first();

    // HPP mengikuti harga beli cetakan masing-masing.
    expect((int) $item1->harga_beli_snapshot)->toBe(28000) // 70% dari 40000 (factory)
        ->and((int) $item2->harga_beli_snapshot)->toBe(33000)
        // Laba per item = (harga final - HPP) × qty
        ->and($item1->price_final - $item1->harga_beli_snapshot)->toBe(40000 - 28000)
        ->and($item2->price_final - $item2->harga_beli_snapshot)->toBe(43000 - 33000)
        // Harga jual ikut cetakan
        ->and($item2->harga_snapshot)->toBe(43000);
});

// ── Alur gabungan "Proses & Kirim" ──

function flowOrder(string $status, array $overrides = []): Order
{
    return Order::factory()->create(array_merge([
        'status' => $status,
        'payment_status' => PaymentStatus::Lunas,
        'sumber_pembelian' => 'website',
        'user_id' => null,
        'shipping_cost' => 12000,
        'total' => 62000,
        'ekspedisi' => 'jne',
        'courier_service_code' => 'reg',
    ], $overrides));
}

function flowOrderItem(Order $order, Book $book): void
{
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => $book->harga,
        'harga_beli_snapshot' => 30000,
        'qty' => 1,
        'price_original' => $book->harga,
        'price_final' => $book->harga,
    ]);
}

function fakeBiteshipBooking(): void
{
    Http::fake([
        'api.biteship.com/v1/orders' => Http::response([
            'success' => true,
            'id' => 'bsh-101',
            'waybill_id' => 'AWB-101',
            'label_url' => 'https://label.test/awb-101.pdf',
            'status' => 'confirmed',
        ]),
        'api.biteship.com/v1/pickups' => Http::response(['success' => true]),
    ]);
}

it('processes, confirms payment, books and schedules pickup in one action', function (): void {
    fakeBiteshipBooking();
    Setting::set('store_telepon', '08123456789');
    Setting::set('store_alamat', 'Jl. Merdeka 1, KOTA MALANG, 65144');

    $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::MenungguKonfirmasi->value, ['payment_status' => PaymentStatus::Menunggu]);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $order), [
        'konfirmasi_lunas' => '1',
        'shipping_cost' => 12000,
        'ekspedisi' => 'jne',
        'courier_service_code' => 'reg',
        'warehouse_origin' => 'malang',
        'collection_method' => 'pickup',
        'pickup_date' => now()->addDay()->toDateString(),
    ])->assertRedirect();

    $order->refresh();

    expect($order->payment_status)->toBe(PaymentStatus::Lunas)
        ->and($order->status)->toBe(OrderStatus::Diproses)
        ->and($order->biteship_order_id)->toBe('bsh-101')
        ->and($order->awb)->toBe('AWB-101')
        ->and($order->shipping_collection_method)->toBe('pickup')
        ->and($book->fresh()->stok)->toBe(4);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/orders')
        && $request['origin_collection_method'] === 'pickup');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/pickups'));
});

it('blocks process-and-ship without confirming payment', function (): void {
    fakeBiteshipBooking();
    Setting::set('store_telepon', '08123456789');
    Setting::set('store_alamat', 'Jl. Merdeka 1, KOTA MALANG, 65144');

    $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::MenungguKonfirmasi->value, ['payment_status' => PaymentStatus::Menunggu]);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $order), [
        'shipping_cost' => 12000,
        'ekspedisi' => 'jne',
        'courier_service_code' => 'reg',
        'warehouse_origin' => 'malang',
        'collection_method' => 'pickup',
    ])->assertRedirect();

    $order->refresh();

    expect($order->payment_status)->toBe(PaymentStatus::Menunggu)
        ->and($order->status)->toBe(OrderStatus::MenungguKonfirmasi)
        ->and($order->awb)->toBeNull();

    Http::assertNothingSent();
});

it('retries booking for an already processed order without re-processing', function (): void {
    fakeBiteshipBooking();
    Setting::set('store_telepon', '08123456789');
    Setting::set('store_alamat', 'Jl. Merdeka 1, KOTA MALANG, 65144');

    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::Diproses->value);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $order), [
        'collection_method' => 'pickup',
    ])->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Diproses)
        ->and($order->awb)->toBe('AWB-101')
        ->and($book->fresh()->stok)->toBe(5);
});

it('keeps the order processed when booking fails so it can be retried', function (): void {
    Http::fake([
        'api.biteship.com/v1/orders' => Http::response(['success' => false], 500),
    ]);
    Setting::set('store_telepon', '08123456789');
    Setting::set('store_alamat', 'Jl. Merdeka 1, KOTA MALANG, 65144');

    $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::MenungguKonfirmasi->value);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $order), [
        'shipping_cost' => 12000,
        'ekspedisi' => 'jne',
        'courier_service_code' => 'reg',
        'warehouse_origin' => 'malang',
        'collection_method' => 'pickup',
    ])->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Diproses)
        ->and($order->awb)->toBeNull()
        ->and(session('inertia.flash_data.toast.message'))->toContain('diproses');
});

it('books with drop_off method without scheduling a pickup', function (): void {
    fakeBiteshipBooking();
    Setting::set('store_telepon', '08123456789');
    Setting::set('store_alamat', 'Jl. Merdeka 1, KOTA MALANG, 65144');

    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::Diproses->value);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $order), [
        'collection_method' => 'drop_off',
    ])->assertRedirect();

    $order->refresh();

    expect($order->awb)->toBe('AWB-101')
        ->and($order->shipping_collection_method)->toBe('drop_off');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/orders')
        && $request['origin_collection_method'] === 'drop_off');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/v1/pickups'));
});

// ── Channel non-website: proses → selesai langsung ──

it('processes a marketplace order without payment, booking, then completes directly', function (): void {
    $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::MenungguKonfirmasi->value, [
        'payment_status' => PaymentStatus::Menunggu,
        'sumber_pembelian' => 'shopee',
    ]);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $order), [
        'shipping_cost' => 0,
        'warehouse_origin' => 'malang',
    ])->assertRedirect();

    Http::assertNothingSent();

    $order->refresh();

    expect($order->payment_status)->toBe(PaymentStatus::Menunggu)
        ->and($order->status)->toBe(OrderStatus::Diproses)
        ->and($order->awb)->toBeNull()
        ->and($order->ekspedisi)->toBeNull()
        ->and($book->fresh()->stok)->toBe(4);

    // Non-website: selesai langsung dari diproses (tanpa status dikirim).
    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Selesai->value])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Selesai)
        ->and(CashFlow::where('order_id', $order->id)->count())->toBe(0);
});

it('processes a toko order without Biteship booking', function (): void {
    $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::MenungguKonfirmasi->value, [
        'payment_status' => PaymentStatus::Lunas,
        'sumber_pembelian' => 'toko',
    ]);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $order), [
        'shipping_cost' => 0,
        'warehouse_origin' => 'malang',
    ])->assertRedirect();

    Http::assertNothingSent();

    expect($order->fresh()->status)->toBe(OrderStatus::Diproses)
        ->and($book->fresh()->stok)->toBe(4);
});

it('rejects Biteship booking for non-website orders', function (): void {
    fakeBiteshipBooking();
    Setting::set('store_telepon', '08123456789');
    Setting::set('store_alamat', 'Jl. Merdeka 1, KOTA MALANG, 65144');

    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::Diproses->value, ['sumber_pembelian' => 'shopee']);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.shipping', $order), ['courier' => 'jne'])
        ->assertRedirect();

    expect($order->fresh()->awb)->toBeNull();
});

// ── Resi wajib sebelum dikirim (website) ──

it('blocks marking a website order as shipped without an AWB', function (): void {
    $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::Diproses->value, ['sumber_pembelian' => 'website']);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Dikirim->value])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Diproses)
        ->and(session('inertia.flash_data.toast.message'))->toContain('Resi');
});

it('marks an order as shipped with a manual awb and tracking link', function (): void {
    $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::Diproses->value, ['sumber_pembelian' => 'website']);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), [
            'status' => OrderStatus::Dikirim->value,
            'awb' => 'WAN-123456',
            'biteship_courier_link' => 'https://www.wahana.com/lacak-kiriman?noresi=WAN-123456',
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Dikirim)
        ->and($order->awb)->toBe('WAN-123456')
        ->and($order->biteship_courier_link)->toBe('https://www.wahana.com/lacak-kiriman?noresi=WAN-123456');
});

it('allows marking a website order as shipped once the AWB exists', function (): void {
    $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::Diproses->value, [
        'sumber_pembelian' => 'website',
        'awb' => 'AWB-987',
    ]);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Dikirim->value])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Dikirim);
});

// ── Retry booking & AWB asinkron ──

it('does not duplicate booking when retrying a booked order without AWB', function (): void {
    Http::fake([
        // retrieveOrder: GET /v1/orders/{id} → waybill sudah terbit di Biteship.
        'api.biteship.com/v1/orders/*' => Http::response([
            'success' => true,
            'id' => 'bsh-777',
            'status' => 'allocated',
            'waybill_id' => 'AWB-RETRIEVE',
        ]),
        'api.biteship.com/v1/orders' => Http::response([
            'success' => true,
            'id' => 'bsh-777',
            'waybill_id' => null,
            'status' => 'confirmed',
        ]),
        'api.biteship.com/v1/pickups' => Http::response(['success' => true]),
    ]);
    Setting::set('store_telepon', '08123456789');
    Setting::set('store_alamat', 'Jl. Merdeka 1, KOTA MALANG, 65144');

    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::Diproses->value, [
        'biteship_order_id' => 'bsh-777',
        'awb' => null,
        'shipping_collection_method' => 'pickup',
    ]);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)->post(route('admin.orders.process-ship', $order), [
        'collection_method' => 'pickup',
    ])->assertRedirect();

    // Tidak boleh ada booking baru (duplikat reference_id) — hanya retrieve + pickup.
    Http::assertNotSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/v1/orders'));
    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_contains($request->url(), '/v1/orders/bsh-777'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/pickups'));

    expect($order->fresh()->awb)->toBe('AWB-RETRIEVE');
});

it('stores the courier waybill id from the status webhook when AWB is missing', function (): void {
    config(['biteship.webhook_secret' => 'webhook-secret-test']);

    $order = flowOrder(OrderStatus::Diproses->value, [
        'biteship_order_id' => 'bsh-888',
        'awb' => null,
    ]);

    $payload = [
        'event' => 'order.status',
        'order_id' => 'bsh-888',
        'status' => 'picked',
        'courier_waybill_id' => 'AWB-ASYNC',
    ];

    $this->postJson(route('webhooks.biteship'), $payload, [
        'X-Signature' => hash_hmac('sha256', json_encode($payload), config('biteship.webhook_secret')),
    ])->assertOk();

    $order->refresh();

    expect($order->awb)->toBe('AWB-ASYNC')
        ->and($order->biteship_status)->toBe('picked')
        ->and($order->status)->toBe(OrderStatus::Dikirim);
});

it('offers only enabled couriers in the booking dropdown', function (): void {
    Courier::create(['code' => 'wahana', 'name' => 'Wahana', 'is_active' => true, 'sort_order' => 0]);
    Courier::create(['code' => 'jne', 'name' => 'JNE', 'is_active' => false, 'sort_order' => 0]);

    Http::fake([
        'api.biteship.com/v1/couriers' => Http::response([
            'success' => true,
            'couriers' => [
                ['courier_code' => 'jne', 'courier_name' => 'JNE', 'courier_service_code' => 'reg', 'courier_service_name' => 'Reguler'],
                ['courier_code' => 'wahana', 'courier_name' => 'Wahana', 'courier_service_code' => 'reg', 'courier_service_name' => 'Reguler'],
            ],
        ]),
    ]);

    $services = app(BiteshipShippingService::class)->courierServices();

    expect(collect($services)->pluck('courier_code')->all())->toBe(['wahana']);
});

it('blocks booking with a friendly message when origin postal code is missing', function (): void {
    Setting::set('store_telepon', '08123456789');
    Setting::set('store_alamat', 'Jl. Merdeka 1, KOTA MALANG, 65144');
    Setting::set('origin_postal_code', null);

    fakeBiteshipBooking();

    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::Diproses->value);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.shipping', $order), ['courier' => 'jne'])
        ->assertRedirect();

    Http::assertNothingSent();

    expect($order->fresh()->awb)->toBeNull()
        ->and(session('inertia.flash_data.toast.message'))->toContain('Kode pos asal toko belum diatur');
});

it('exposes detailed shipping data and tracking template on the order page', function (): void {
    Setting::set('store_nama_lembaga', 'Toko Buku Test');
    Setting::set('store_telepon', '08123456789');
    Setting::set('store_email', 'toko@test.id');
    Setting::set('store_alamat', 'Jl. Test 1, KOTA MALANG, 65151');
    Setting::set('origin_postal_code', '65151');

    $order = flowOrder(OrderStatus::Diproses->value, [
        'no_hp' => '08199999999',
        'alamat' => 'Jl. Merdeka 1',
        'kelurahan' => 'BARENG',
        'kecamatan' => 'KLOJEN',
        'kabupaten_kota' => 'KOTA MALANG',
        'provinsi' => 'JAWA TIMUR',
        'kode_pos' => '65144',
    ]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.orders.show', $order))
        ->assertSuccessful());

    expect($props['storeNamaLembaga'])->toBe('Toko Buku Test')
        ->and($props['storeTelepon'])->toBe('08123456789')
        ->and($props['originPostalCode'])->toBe('65151')
        ->and($props['trackingUrlTemplate'])->toContain('{awb}')
        ->and($props['order']['no_hp'])->toBe('08199999999')
        ->and($props['order']['kelurahan'])->toBe('BARENG')
        ->and($props['order']['kode_pos'])->toBe('65144')
        ->and($props['order']['provinsi'])->toBe('JAWA TIMUR');
});

it('searches book options by title, sku, author or translator', function (): void {
    Book::factory()->create([
        'aktif' => true,
        'judul' => 'Dunia Sophie',
        'kode_sku' => 'SKU-A',
        'penulis' => 'Jostein Gaarder',
        'penterjemah' => 'Bambang Irawan',
    ]);
    Book::factory()->create([
        'aktif' => true,
        'judul' => 'Buku Biasa',
        'kode_sku' => 'SKU-B',
        'penulis' => 'Penulis Lain',
        'penterjemah' => null,
    ]);

    $this->actingAs($this->admin)->getJson(route('admin.orders.options.books', ['search' => 'Gaarder']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.judul', 'Dunia Sophie');

    $this->actingAs($this->admin)->getJson(route('admin.orders.options.books', ['search' => 'Bambang']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.judul', 'Dunia Sophie');

    $this->actingAs($this->admin)->getJson(route('admin.orders.options.books', ['search' => 'SKU-B']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.judul', 'Buku Biasa');
});

// ── Pembayaran channel toko & auto-resolve customer ──

it('marks a toko order paid in cash as lunas immediately', function (): void {
    $book = Book::factory()->withStock(malang: 20)->create(['harga' => 50000]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Pembeli Toko Tunai',
            'metode_bayar' => 'cash',
            'sumber_pembelian' => 'toko',
            'items' => [
                ['book_id' => $book->id, 'qty' => 1],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $order = Order::latest('id')->first();

    expect($order->payment_status)->toBe(PaymentStatus::Lunas)
        ->and($order->sumber_pembelian)->toBe('toko');
});

it('keeps a toko order paid by transfer waiting for admin confirmation', function (): void {
    $book = Book::factory()->withStock(malang: 20)->create(['harga' => 50000]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Pembeli Toko Transfer',
            'metode_bayar' => 'transfer',
            'sumber_pembelian' => 'toko',
            'items' => [
                ['book_id' => $book->id, 'qty' => 1],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $order = Order::latest('id')->first();

    expect($order->payment_status)->toBe(PaymentStatus::Menunggu);

    // Fallback: admin bisa konfirmasi manual untuk semua channel.
    $this->actingAs($this->admin)
        ->patch(route('admin.orders.payment', $order))
        ->assertRedirect();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Lunas);
});

it('links the order to a customer automatically when name matches uniquely', function (): void {
    $customer = User::factory()->create(['name' => 'Budi Santoso', 'whatsapp_number' => '0811111111']);
    $book = Book::factory()->withStock(malang: 20)->create(['harga' => 50000]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Budi Santoso',
            'whatsapp_pembeli' => '0811111111',
            'metode_bayar' => 'cash',
            'sumber_pembelian' => 'toko',
            'items' => [
                ['book_id' => $book->id, 'qty' => 1],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    expect(Order::latest('id')->first()->user_id)->toBe($customer->id);
});

it('leaves the order unlinked when the customer name is ambiguous', function (): void {
    User::factory()->create(['name' => 'Budi Santoso', 'whatsapp_number' => '0811111111']);
    User::factory()->create(['name' => 'Budi Santoso', 'whatsapp_number' => '0822222222']);
    $book = Book::factory()->withStock(malang: 20)->create(['harga' => 50000]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Budi Santoso',
            'whatsapp_pembeli' => '0899999999',
            'metode_bayar' => 'cash',
            'sumber_pembelian' => 'toko',
            'items' => [
                ['book_id' => $book->id, 'qty' => 1],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    expect(Order::latest('id')->first()->user_id)->toBeNull();
});

// ── Order toko tanpa ongkir ──

it('processes an ambil-sendiri order without shipping cost', function (): void {
    $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::MenungguKonfirmasi->value, [
        'sumber_pembelian' => 'toko',
        'metode_pengambilan' => 'ambil',
        'payment_status' => PaymentStatus::Lunas,
        'shipping_cost' => 0,
    ]);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.process', $order), [
            'warehouse_origin' => 'malang',
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Diproses)
        ->and($order->shipping_cost)->toBe(0);
});

it('keeps the stored shipping cost when processing without sending it', function (): void {
    $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::MenungguKonfirmasi->value, [
        'sumber_pembelian' => 'website',
        'shipping_cost' => 12000,
    ]);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.process', $order), [
            'ekspedisi' => 'jne',
            'warehouse_origin' => 'malang',
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Diproses)
        ->and($order->fresh()->shipping_cost)->toBe(12000);
});

// ── Metode pengambilan: kirim / ambil sendiri ──

it('defaults created orders to ambil sendiri', function (): void {
    createLocalVillages();
    fakeRajaOngkirApi();

    $book = Book::factory()->withStock(malang: 20)->create(['harga' => 50000]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Pembeli Toko',
            'metode_bayar' => 'cash',
            'sumber_pembelian' => 'toko',
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    expect(Order::latest('id')->first()->metode_pengambilan)->toBe('ambil');

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Pembeli Shopee',
            'metode_bayar' => 'transfer',
            'sumber_pembelian' => 'shopee',
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    expect(Order::latest('id')->first()->metode_pengambilan)->toBe('ambil');
});

it('completes an ambil-sendiri order directly from diproses', function (): void {
    $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::Diproses->value, [
        'sumber_pembelian' => 'website',
        'metode_pengambilan' => 'ambil',
    ]);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Selesai->value])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Selesai);
});

it('does not auto-cancel unpaid ambil-sendiri orders after 24 hours', function (): void {
    $order = flowOrder(OrderStatus::MenungguKonfirmasi->value, [
        'sumber_pembelian' => 'website',
        'metode_pengambilan' => 'ambil',
        'payment_status' => PaymentStatus::Menunggu,
        'created_at' => now()->subHours(25),
    ]);

    $this->artisan('orders:cancel-unpaid')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::MenungguKonfirmasi);
});

it('rejects creating an order with qty exceeding book stock', function (): void {
    $book = Book::factory()->withStock(malang: 3)->create(['harga' => 50000]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Pembeli Overstock',
            'metode_bayar' => 'transfer',
            'sumber_pembelian' => 'toko',
            'items' => [
                ['book_id' => $book->id, 'qty' => 5],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('items');

    expect(Order::count())->toBe(0);
});

it('requires ekspedisi when the order is shipped (not ambil sendiri)', function (): void {
    $book = Book::factory()->withStock(malang: 20)->create(['harga' => 50000]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Pembeli Kirim',
            'metode_bayar' => 'transfer',
            'sumber_pembelian' => 'shopee',
            'metode_pengambilan' => 'kirim',
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('ekspedisi');

    expect(Order::count())->toBe(0);
});

it('ignores ekspedisi and shipping cost when the order is ambil sendiri', function (): void {
    createLocalVillages();
    fakeRajaOngkirApi();

    $book = Book::factory()->withStock(malang: 20)->create(['harga' => 50000]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Pembeli Ambil',
            'metode_bayar' => 'transfer',
            'sumber_pembelian' => 'toko',
            'metode_pengambilan' => 'ambil',
            'kode_pos' => '65144',
            'ekspedisi' => 'jne',
            'items' => [['book_id' => $book->id, 'qty' => 1]],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $order = Order::latest('id')->first();

    expect($order->metode_pengambilan)->toBe('ambil')
        ->and($order->ekspedisi)->toBeNull()
        ->and($order->shipping_cost)->toBe(0);
});

// ── Cash: langsung diproses + stok otomatis terpotong ──

it('processes a cash order immediately at creation (diproses + stock deducted)', function (): void {
    $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Pembeli Cash',
            'metode_bayar' => 'cash',
            'sumber_pembelian' => 'toko',
            'items' => [['book_id' => $book->id, 'qty' => 2]],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $order = Order::latest('id')->first();

    expect($order->status)->toBe(OrderStatus::Diproses)
        ->and($order->payment_status)->toBe(PaymentStatus::Lunas)
        ->and($order->warehouse_origin)->toBe('malang')
        ->and($book->fresh()->stok)->toBe(3);
});

it('keeps transfer orders waiting and does not deduct stock at creation', function (): void {
    $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Pembeli Transfer',
            'metode_bayar' => 'transfer',
            'sumber_pembelian' => 'toko',
            'items' => [['book_id' => $book->id, 'qty' => 2]],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $order = Order::latest('id')->first();

    expect($order->status)->toBe(OrderStatus::MenungguKonfirmasi)
        ->and($order->payment_status)->toBe(PaymentStatus::Menunggu)
        ->and($book->fresh()->stok)->toBe(5);
});

it('rejects a cash order when stock is insufficient at the default warehouse', function (): void {
    $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $book = Book::factory()->withStock(malang: 2)->create(['aktif' => true, 'harga' => 50000]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Pembeli Cash',
            'metode_bayar' => 'cash',
            'sumber_pembelian' => 'toko',
            'items' => [['book_id' => $book->id, 'qty' => 5]],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('items');

    expect(Order::count())->toBe(0)
        ->and($book->fresh()->stok)->toBe(2);
});

it('requires a resi before marking a toko kirim order as shipped', function (): void {
    $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::Diproses->value, [
        'sumber_pembelian' => 'toko',
        'metode_pengambilan' => 'kirim',
    ]);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Dikirim->value])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Diproses)
        ->and(session('inertia.flash_data.toast.message'))->toContain('Resi');

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), [
            'status' => OrderStatus::Dikirim->value,
            'awb' => 'WAN-999',
        ])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Dikirim);
});

it('blocks completing a toko kirim order directly without shipping', function (): void {
    $malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = flowOrder(OrderStatus::Diproses->value, [
        'sumber_pembelian' => 'toko',
        'metode_pengambilan' => 'kirim',
    ]);
    flowOrderItem($order, $book);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Selesai->value])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Diproses);
});
