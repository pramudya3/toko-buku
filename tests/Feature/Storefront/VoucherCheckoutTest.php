<?php

use App\Enums\OrderStatus;
use App\Models\Book;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Services\OrderStatusService;
use App\Services\PricingService;
use App\Services\VoucherService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->customer = User::factory()->create();
    Setting::set('origin_postal_code', '65144');
});

it('lists only usable vouchers on the checkout page', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);

    $usable = Voucher::factory()->percentage(10)->create(['min_order_amount' => 50000]);
    $expired = Voucher::factory()->expired()->create();

    $this->actingAs($this->customer)->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 2]);

    $props = inertiaProps($this->actingAs($this->customer)
        ->get(route('checkout.index'))
        ->assertOk());

    $ids = collect($props['vouchers'])->pluck('id')->all();

    expect($ids)->toContain($usable->id)
        ->and($ids)->not->toContain($expired->id);
});

it('applies a voucher discount when checking out (ambil sendiri)', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);
    $voucher = Voucher::factory()->percentage(10)->create(['min_order_amount' => 0]);

    $this->actingAs($this->customer)->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 2]);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'nama_pembeli' => 'Pembeli Voucher',
            'metode_bayar' => 'transfer',
            'metode_pengambilan' => 'ambil',
            'selected_groups' => ['regular'],
            'voucher_id' => $voucher->id,
        ])
        ->assertRedirect();

    $order = Order::latest('id')->firstOrFail();

    expect($order->total)->toBe(90000) // 100.000 - 10%
        ->and($order->voucher_discount_amount)->toBe(10000)
        ->and($order->voucher_code_snapshot)->toBe($voucher->kode)
        ->and($order->voucher_id)->toBe($voucher->id)
        ->and(VoucherUsage::where('voucher_id', $voucher->id)->where('order_id', $order->id)->exists())->toBeTrue();
});

it('applies a fixed voucher capped at the subtotal', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);
    $voucher = Voucher::factory()->fixed(80000)->create();

    $this->actingAs($this->customer)->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 1]);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'nama_pembeli' => 'Pembeli Voucher',
            'metode_bayar' => 'transfer',
            'metode_pengambilan' => 'ambil',
            'selected_groups' => ['regular'],
            'voucher_id' => $voucher->id,
        ])
        ->assertRedirect();

    $order = Order::latest('id')->firstOrFail();

    expect($order->total)->toBe(0) // 50.000 - 50.000 (cap)
        ->and($order->voucher_discount_amount)->toBe(50000);
});

it('rejects a voucher whose global quota is exhausted', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);
    $voucher = Voucher::factory()->percentage(10)->create(['max_uses' => 1]);
    $otherUser = User::factory()->create();

    VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'order_id' => Order::factory()->create(['user_id' => $otherUser->id])->id,
        'user_id' => $otherUser->id,
    ]);

    $this->actingAs($this->customer)->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 2]);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'nama_pembeli' => 'Pembeli Voucher',
            'metode_bayar' => 'transfer',
            'metode_pengambilan' => 'ambil',
            'selected_groups' => ['regular'],
            'voucher_id' => $voucher->id,
        ])
        ->assertSessionHasErrors('items');

    expect(VoucherUsage::where('voucher_id', $voucher->id)->count())->toBe(1);
});

it('rejects a voucher beyond the per-user quota', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);
    $voucher = Voucher::factory()->percentage(10)->create(['max_uses_per_user' => 1]);

    VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'order_id' => Order::factory()->create(['user_id' => $this->customer->id])->id,
        'user_id' => $this->customer->id,
    ]);

    $this->actingAs($this->customer)->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 2]);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'nama_pembeli' => 'Pembeli Voucher',
            'metode_bayar' => 'transfer',
            'metode_pengambilan' => 'ambil',
            'selected_groups' => ['regular'],
            'voucher_id' => $voucher->id,
        ])
        ->assertSessionHasErrors('items');

    expect(VoucherUsage::where('voucher_id', $voucher->id)->count())->toBe(1);
});

it('rejects an expired voucher', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);
    $voucher = Voucher::factory()->expired()->create();

    $this->actingAs($this->customer)->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 2]);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'nama_pembeli' => 'Pembeli Voucher',
            'metode_bayar' => 'transfer',
            'metode_pengambilan' => 'ambil',
            'selected_groups' => ['regular'],
            'voucher_id' => $voucher->id,
        ])
        ->assertSessionHasErrors('items');

    expect(VoucherUsage::where('voucher_id', $voucher->id)->doesntExist())->toBeTrue();
});

it('rejects a voucher when the minimum order amount is not met', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);
    $voucher = Voucher::factory()->percentage(10)->create(['min_order_amount' => 200000]);

    $this->actingAs($this->customer)->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 2]);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'nama_pembeli' => 'Pembeli Voucher',
            'metode_bayar' => 'transfer',
            'metode_pengambilan' => 'ambil',
            'selected_groups' => ['regular'],
            'voucher_id' => $voucher->id,
        ])
        ->assertSessionHasErrors('items');

    expect(VoucherUsage::where('voucher_id', $voucher->id)->doesntExist())->toBeTrue();
});

it('still records net revenue (after voucher) when order completes', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);
    $voucher = Voucher::factory()->percentage(10)->create();

    $this->actingAs($this->customer)->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 2]);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'nama_pembeli' => 'Pembeli Voucher',
            'metode_bayar' => 'transfer',
            'metode_pengambilan' => 'ambil',
            'selected_groups' => ['regular'],
            'voucher_id' => $voucher->id,
        ])
        ->assertRedirect();

    $order = Order::latest('id')->firstOrFail();

    // Revenue order (neto setelah voucher) dipakai laporan & arus kas.
    expect($order->subtotal())->toBe(90000)
        ->and($order->itemsTotal())->toBe(100000);
});

it('applies an ongkir voucher to the shipping cost', function (): void {
    Http::fake([
        'rajaongkir.komerce.id/api/v1/destination/domestic-destination*' => Http::response([
            'meta' => ['message' => 'ok', 'code' => 200, 'status' => 'success'],
            'data' => [[
                'id' => 700114,
                'label' => 'Test 65144',
                'province_name' => 'JAWA TIMUR',
                'city_name' => 'KOTA MALANG',
                'district_name' => 'KLOJEN',
                'subdistrict_name' => 'BARENG',
                'zip_code' => '65144',
            ]],
        ]),
        'rajaongkir.komerce.id/api/v1/calculate/domestic-cost' => function (Request $request) {
            return Http::response([
                'meta' => ['message' => 'ok', 'code' => 200, 'status' => 'success'],
                'data' => $request['courier'] === 'jne' ? [
                    ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => 'Reguler', 'cost' => 12000, 'etd' => '1-2'],
                ] : [],
            ]);
        },
    ]);

    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);
    $voucher = Voucher::factory()->ongkir()->percentage(10)->create();

    $this->actingAs($this->customer)->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 2]);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'nama_pembeli' => 'Pembeli Ongkir',
            'metode_bayar' => 'transfer',
            'kode_pos' => '65144',
            'kelurahan' => 'BARENG',
            'ekspedisi' => 'jne',
            'selected_groups' => ['regular'],
            'voucher_id' => $voucher->id,
        ])
        ->assertRedirect();

    $order = Order::latest('id')->firstOrFail();

    // 100.000 item + 12.000 ongkir − 10% ongkir (1.200) = 110.800.
    expect($order->total)->toBe(110800)
        ->and($order->shipping_cost)->toBe(12000)
        ->and($order->voucher_discount_amount)->toBe(1200)
        ->and($order->voucher_scope_snapshot)->toBe('ongkir')
        ->and($order->itemsTotal())->toBe(100000)
        ->and(VoucherUsage::where('order_id', $order->id)->exists())->toBeTrue();
});

it('caps an ongkir voucher at the shipping cost', function (): void {
    Http::fake([
        'rajaongkir.komerce.id/api/v1/destination/domestic-destination*' => Http::response([
            'meta' => ['message' => 'ok', 'code' => 200, 'status' => 'success'],
            'data' => [[
                'id' => 700114,
                'label' => 'Test 65144',
                'province_name' => 'JAWA TIMUR',
                'city_name' => 'KOTA MALANG',
                'district_name' => 'KLOJEN',
                'subdistrict_name' => 'BARENG',
                'zip_code' => '65144',
            ]],
        ]),
        'rajaongkir.komerce.id/api/v1/calculate/domestic-cost' => function (Request $request) {
            return Http::response([
                'meta' => ['message' => 'ok', 'code' => 200, 'status' => 'success'],
                'data' => $request['courier'] === 'jne' ? [
                    ['name' => 'JNE', 'code' => 'jne', 'service' => 'REG', 'description' => 'Reguler', 'cost' => 12000, 'etd' => '1-2'],
                ] : [],
            ]);
        },
    ]);

    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);
    $voucher = Voucher::factory()->ongkir()->fixed(50000)->create();

    $this->actingAs($this->customer)->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 1]);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'nama_pembeli' => 'Pembeli Ongkir',
            'metode_bayar' => 'transfer',
            'kode_pos' => '65144',
            'kelurahan' => 'BARENG',
            'ekspedisi' => 'jne',
            'selected_groups' => ['regular'],
            'voucher_id' => $voucher->id,
        ])
        ->assertRedirect();

    $order = Order::latest('id')->firstOrFail();

    // Diskon ongkir tidak pernah melebihi ongkir itu sendiri (50.000 > 12.000).
    expect($order->total)->toBe(50000)
        ->and($order->voucher_discount_amount)->toBe(12000);
});

it('rejects an ongkir voucher when the order is picked up (ambil sendiri)', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);
    $voucher = Voucher::factory()->ongkir()->percentage(10)->create();

    $this->actingAs($this->customer)->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 2]);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'nama_pembeli' => 'Pembeli Ongkir',
            'metode_bayar' => 'transfer',
            'metode_pengambilan' => 'ambil',
            'selected_groups' => ['regular'],
            'voucher_id' => $voucher->id,
        ])
        ->assertSessionHasErrors('items');

    expect(VoucherUsage::where('voucher_id', $voucher->id)->doesntExist())->toBeTrue();
});

it('releases the voucher usage when the order is cancelled', function (): void {
    $voucher = Voucher::factory()->percentage(10)->create(['max_uses' => 1]);
    $order = Order::factory()->create([
        'user_id' => $this->customer->id,
        'status' => OrderStatus::MenungguKonfirmasi,
        'voucher_id' => $voucher->id,
    ]);

    VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'order_id' => $order->id,
        'user_id' => $this->customer->id,
    ]);

    app(OrderStatusService::class)->transition($order, OrderStatus::Batal);

    expect(VoucherUsage::where('order_id', $order->id)->doesntExist())->toBeTrue()
        ->and(app(VoucherService::class)->availableFor($this->customer, 100000)->pluck('id'))
        ->toContain($voucher->id);
});

it('lists active vouchers on the promo page', function (): void {
    $voucher = Voucher::factory()->percentage(10)->create();
    $expired = Voucher::factory()->expired()->create();

    $props = inertiaProps($this->get(route('books.promo'))->assertOk());

    $ids = collect($props['vouchers'])->pluck('id')->all();

    expect($ids)->toContain($voucher->id)
        ->and($ids)->not->toContain($expired->id);
});

it('keeps the frozen voucher discount when the order is re-priced', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);
    $voucher = Voucher::factory()->percentage(10)->create(['min_order_amount' => 0]);

    $this->actingAs($this->customer)->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 2]);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'nama_pembeli' => 'Pembeli Voucher',
            'metode_bayar' => 'transfer',
            'metode_pengambilan' => 'ambil',
            'selected_groups' => ['regular'],
            'voucher_id' => $voucher->id,
        ])
        ->assertRedirect();

    $order = Order::latest('id')->firstOrFail();

    expect($order->total)->toBe(90000)
        ->and($order->voucher_discount_amount)->toBe(10000);

    // Simulasi re-pricing saat order diproses admin (applyToOrder dipanggil ulang).
    app(PricingService::class)->applyToOrder($order);
    $order->refresh();

    expect($order->total)->toBe(90000)
        ->and($order->voucher_discount_amount)->toBe(10000)
        ->and($order->voucher_code_snapshot)->toBe($voucher->kode);
});

it('keeps the frozen discount when the voucher is deleted after checkout', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);
    $voucher = Voucher::factory()->percentage(10)->create(['min_order_amount' => 0]);

    $this->actingAs($this->customer)->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 2]);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'nama_pembeli' => 'Pembeli Voucher',
            'metode_bayar' => 'transfer',
            'metode_pengambilan' => 'ambil',
            'selected_groups' => ['regular'],
            'voucher_id' => $voucher->id,
        ])
        ->assertRedirect();

    $order = Order::latest('id')->firstOrFail();

    $voucher->delete();

    // Re-pricing tidak boleh menghapus diskon yang sudah disepakati customer.
    app(PricingService::class)->applyToOrder($order);
    $order->refresh();

    expect($order->total)->toBe(90000)
        ->and($order->voucher_discount_amount)->toBe(10000)
        ->and($order->voucher_code_snapshot)->toBe($voucher->kode);
});

it('keeps the frozen discount when the voucher terms change after checkout', function (): void {
    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);
    $voucher = Voucher::factory()->percentage(10)->create(['min_order_amount' => 0]);

    $this->actingAs($this->customer)->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 2]);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'nama_pembeli' => 'Pembeli Voucher',
            'metode_bayar' => 'transfer',
            'metode_pengambilan' => 'ambil',
            'selected_groups' => ['regular'],
            'voucher_id' => $voucher->id,
        ])
        ->assertRedirect();

    $order = Order::latest('id')->firstOrFail();

    $voucher->update(['discount_percentage' => 20]);

    // Re-pricing memakai nilai tersimpan, bukan syarat voucher terbaru.
    app(PricingService::class)->applyToOrder($order);
    $order->refresh();

    expect($order->total)->toBe(90000)
        ->and($order->voucher_discount_amount)->toBe(10000);
});
