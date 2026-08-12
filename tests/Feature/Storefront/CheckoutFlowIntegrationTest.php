<?php

use App\Enums\OrderStatus;
use App\Models\Book;
use App\Models\CashFlow;
use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);
});

it('runs the full order lifecycle: cart → checkout → process → complete → sales report', function (): void {
    Http::fake([
        'api.biteship.com/*' => Http::response([
            'success' => true,
            'pricing' => [[
                'courier_code' => 'jne',
                'courier_name' => 'JNE',
                'courier_service_name' => 'Reguler',
                'price' => 12000,
                'duration' => '1 - 2 days',
            ]],
        ]),
    ]);

    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000, 'berat_gr' => 1000]);

    // 1. Pembeli menambahkan ke keranjang & checkout.
    $this->post(route('cart.add'), ['book_id' => $book->id, 'qty' => 2])->assertRedirect();

    $this->post(route('checkout.store'), [
        'nama_pembeli' => 'Pembeli Integrasi',
        'whatsapp_pembeli' => '08123456789',
        'metode_bayar' => 'transfer',
        'kode_pos' => '65144',
        'ekspedisi' => 'jne',
        'selected_groups' => ['regular'],
    ])->assertRedirect();

    $order = Order::latest('id')->firstOrFail();

    expect($order->status)->toBe(OrderStatus::MenungguKonfirmasi)
        ->and($order->sumber_pembelian)->toBe('website')
        ->and($order->items()->count())->toBe(1)
        ->and($order->shipping_cost)->toBe(12000)
        ->and($order->total)->toBe(112000)
        // Stok belum ter-deduk sebelum diproses.
        ->and($book->fresh()->stok)->toBe(10);

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

    // 3. Admin mengirim, lalu menuntaskan order → 2 cash flow (pendapatan + biaya ongkir).
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
        ->and(CashFlow::where('order_id', $order->id)->count())->toBe(2);

    $income = CashFlow::where('order_id', $order->id)->where('flow_type', 'revenue')->first();
    $expense = CashFlow::where('order_id', $order->id)->where('flow_type', 'shipping')->first();

    expect($income)->not->toBeNull()
        ->and($expense)->not->toBeNull()
        ->and($income->amount)->toBe(100000) // subtotal produk
        ->and($expense->amount)->toBe(12000);

    // 4. Laporan penjualan menampilkan baris order ini (HPP & laba).
    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.sales-reports.index'))
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
    $this->withSession(['cart' => [
        '19:1' => ['qty' => 1, 'edition_id' => 1],
        $book->id => ['qty' => 2, 'edition_id' => null],
    ]])->get(route('checkout.index'));

    expect(session('cart'))->toBe([$book->id => ['qty' => 2, 'edition_id' => null]]);

    // Toast info dikirim lewat flash Inertia (level page, bukan props).
    $page = $this->withSession(['cart' => [
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

    $this->withSession(['cart' => [
        $book->id.':99' => ['qty' => 1, 'edition_id' => 99],
    ]])->get(route('checkout.index'));

    expect(session('cart'))->toBe([$book->id.':99' => ['qty' => 1, 'edition_id' => null]]);

    $this->get(route('checkout.index'))->assertOk();
});
