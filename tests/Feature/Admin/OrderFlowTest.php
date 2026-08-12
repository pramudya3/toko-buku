<?php

use App\Enums\OrderStatus;
use App\Models\Book;
use App\Models\CashFlow;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderStatusService;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('creates a manual order and calculates prices via PricingService (ORD-03, ORD-08)', function (): void {
    $book = Book::factory()->withStock(malang: 20)->create(['harga' => 50000]);
    $customer = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'user_id' => $customer->id,
            'nama_pembeli' => $customer->name,
            'metode_bayar' => 'transfer',
            'sumber_pembelian' => 'shopee',
            'items' => [
                ['book_id' => $book->id, 'qty' => 2],
            ],
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $order = Order::latest('id')->first();

    expect($order->status)->toBe(OrderStatus::MenungguKonfirmasi)
        ->and($order->sumber_pembelian)->toBe('shopee')
        ->and($order->total)->toBe(100000)
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

it('checks shipping cost from admin endpoint (cached shared)', function (): void {
    createLocalVillages();
    fakeBiteshipApi();

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
    fakeBiteshipApi();

    $book = Book::factory()->withStock(malang: 20)->create(['harga' => 50000, 'berat_gr' => 500]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Pembeli Langsung',
            'metode_bayar' => 'transfer',
            'kode_pos' => '65144',
            'ekspedisi' => 'jne',
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
        ->and($order->ongkir_estimasi)->toBe('1 - 2 days')
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
    $book = Book::factory()->withStock()->create(['harga' => 10000]);

    foreach (range(1, 3) as $index) {
        $this->actingAs($this->admin)
            ->post(route('admin.orders.store'), [
                'nama_pembeli' => 'Pembeli '.$index,
                'metode_bayar' => 'transfer',
                'items' => [['book_id' => $book->id, 'qty' => 1]],
            ]);
    }

    $numbers = Order::pluck('no_order');

    expect($numbers->unique()->count())->toBe(3);
});

it('creates dropship order with end-customer data (DROP-01)', function (): void {
    $book = Book::factory()->withStock()->create(['harga' => 10000]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.store'), [
            'nama_pembeli' => 'Reseller',
            'metode_bayar' => 'transfer',
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
    $order = Order::factory()->create();
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
    $order = Order::factory()->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), [
            'status' => OrderStatus::Diproses->value,
        ])
        ->assertSessionHasErrors('status');

    expect($order->fresh()->status)->toBe(OrderStatus::MenungguKonfirmasi);
});

it('completes an order: stock was reserved at processing, selesai records 2 cash flows (ORD-06, BR-06, CF-03)', function (): void {
    $book = Book::factory()->withStock(malang: 10, sidoarjo: 0)->create(['harga' => 50000]);
    $order = Order::factory()->create();
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
        ->and(CashFlow::where('order_id', $order->id)->where('flow_type', 'revenue')->exists())->toBeTrue()
        ->and(CashFlow::where('order_id', $order->id)->where('flow_type', 'shipping')->exists())->toBeTrue()
        ->and(CashFlow::where('order_id', $order->id)->sum('amount'))->toBe(115000);
});

it('is idempotent: completing twice does not double deduct or duplicate entries (ORD-07)', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 50000]);
    $order = Order::factory()->create();
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
        ->and(CashFlow::where('order_id', $order->id)->count())->toBe(2);
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
        ->and(CashFlow::where('order_id', $order->id)->count())->toBe(2);
});

it('restores reserved stock when cancelling a processed order', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 50000]);
    $order = Order::factory()->create();
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
        ->and(CashFlow::where('order_id', $order->id)->count())->toBe(0)
        // Ada mutasi masuk (restore) setelah mutasi keluar (reserve).
        ->and(InventoryMovement::where('book_id', $book->id)->where('type', 'in')->count())->toBe(1);
});

it('rejects cancelling an order that has been shipped', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 50000]);
    $order = Order::factory()->create();
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
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Dikirim->value])
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

it('cancels an order without side effects (CF-04)', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['harga' => 50000]);
    $order = Order::factory()->create();
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
        ->and(CashFlow::where('order_id', $order->id)->count())->toBe(0);
});

it('shows order detail with items and pricing breakdown (ORD-02)', function (): void {
    $book = Book::factory()->withStock()->create(['harga' => 50000]);
    $order = Order::factory()->create();
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
