<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Book;
use App\Models\CashFlow;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->customer = User::factory()->create();
    $this->malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);

    Setting::set('origin_postal_code', '65144');
});

it('redirects guests away from checkout and shipping cost', function (): void {
    $this->get(route('checkout.index'))->assertRedirect(route('login'));
    $this->post(route('checkout.shipping-cost'))->assertRedirect(route('login'));
});

it('runs the full order lifecycle: cart → checkout → process → complete → sales report', function (): void {
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

    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000, 'berat_gr' => 1000]);

    // 1. Pembeli menambahkan ke keranjang (boleh guest), lalu checkout (wajib login).
    $this->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 2])->assertRedirect();

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'nama_pembeli' => 'Pembeli Integrasi',
        'whatsapp_pembeli' => '08123456789',
        'metode_bayar' => 'transfer',
        'kode_pos' => '65144',
        'kelurahan' => 'BARENG',
        'ekspedisi' => 'jne',
        'selected_groups' => ['regular'],
    ])->assertRedirect();

    $order = Order::latest('id')->firstOrFail();

    expect($order->status)->toBe(OrderStatus::MenungguKonfirmasi)
        ->and($order->sumber_pembelian)->toBe('website')
        ->and($order->kelurahan)->toBe('BARENG')
        ->and($order->items()->count())->toBe(1)
        ->and($order->shipping_cost)->toBe(12000)
        ->and($order->total)->toBe(112000)
        // Stok belum ter-deduk sebelum diproses.
        ->and($book->fresh()->stok)->toBe(10);

    // 1b. Pembeli sudah transfer — admin konfirmasi lunas sebelum proses.
    $order->update(['payment_status' => PaymentStatus::Lunas]);

    // 2. Admin memproses order (isi ongkir final + gudang asal) → stok ter-deduk.
    $this->actingAs($this->admin)
        ->patch(route('admin.orders.process', $order), [
            'shipping_cost' => 12000,
            'ekspedisi' => 'jne',
            'warehouse_origin' => 'malang',
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Diproses)
        // Stok ter-reserve saat diproses (bukan menunggu sampai selesai).
        ->and($book->fresh()->stok)->toBe(8);

    // 3. Admin mengirim (AWB sudah terbit via booking Biteship), lalu
    // menuntaskan order → 2 cash flow (pendapatan + biaya ongkir).
    $order->update(['awb' => 'AWB-INTEGRASI', 'biteship_order_id' => 'bsh-1']);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Dikirim->value])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Selesai->value])
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Selesai)
        // Stok sudah ter-reserve saat diproses — selesai hanya mencatat arus kas.
        ->and($book->fresh()->stok)->toBe(8)
        // OPSI A: Kas mandiri — order selesai tidak buat cash flow.
        ->and(CashFlow::where('order_id', $order->id)->count())->toBe(0);

    // 4. Laporan penjualan menampilkan baris order ini (HPP & laba).
    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.sales-reports.index', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ]))
        ->assertSuccessful());

    $row = collect($props['rows']['data'])->firstWhere('no_order', $order->no_order);

    expect($row)->not->toBeNull()
        ->and($row['qty'])->toBe(2)
        ->and($row['harga_final'])->toBe(50000)
        ->and($row['laba'])->toBe(50000 * 2 - $row['hpp'] * 2);

    // 5. Stok akhir konsisten (10 - 2 = 8) & mutasi tercatat.
    expect($book->fresh()->stok)->toBe(8)
        ->and(app(InventoryService::class)->availableStock($book->fresh()))->toBe(8);
});

it('drops legacy integer cart entries and notifies the user', function (): void {
    $book = Book::factory()->create(['aktif' => true, 'harga' => 50000]);

    // Keranjang sisa sebelum konversi UUID: id buku & cetakan integer.
    $this->actingAs($this->customer)->withSession(['cart' => [
        '19:1' => ['qty' => 1, 'edition_id' => 1],
        $book->id => ['qty' => 2, 'edition_id' => null],
    ]])->get(route('checkout.index'));

    expect(session('cart'))->toBe([$book->id => ['qty' => 2, 'edition_id' => null]]);

    // Toast info dikirim lewat flash Inertia (level page, bukan props).
    $page = $this->actingAs($this->customer)->withSession(['cart' => [
        '19:1' => ['qty' => 1, 'edition_id' => 1],
        $book->id => ['qty' => 2, 'edition_id' => null],
    ]])->get(route('checkout.index'))
        ->assertOk()
        ->viewData('page');

    expect($page['flash']['toast'])->toMatchArray([
        'type' => 'info',
        'message' => '1 item lama di keranjang dihapus karena tidak tersedia lagi.',
    ]);
});

it('resets legacy integer edition ids to the default edition', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);

    $this->actingAs($this->customer)->withSession(['cart' => [
        $book->id.':99' => ['qty' => 1, 'edition_id' => 99],
    ]])->get(route('checkout.index'));

    expect(session('cart'))->toBe([$book->id.':99' => ['qty' => 1, 'edition_id' => null]]);

    $this->actingAs($this->customer)->get(route('checkout.index'))->assertOk();
});
