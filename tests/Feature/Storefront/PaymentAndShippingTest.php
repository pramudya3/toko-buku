<?php

use App\Console\Commands\CancelUnpaidOrders;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->customer = User::factory()->create();
    $this->malang = Warehouse::firstOrCreate(['kode' => 'malang'], ['nama' => 'Malang', 'is_active' => true]);

    config(['biteship.webhook_secret' => 'webhook-secret-test']);

    // Data origin toko — wajib sebelum booking Biteship.
    Setting::set('store_telepon', '08123456789');
    Setting::set('store_alamat', 'Jl. Merdeka 1, KLOJEN, KOTA MALANG, JAWA TIMUR, 65144');
});

function orderIn(string $status, array $overrides = []): Order
{
    return Order::factory()->create(array_merge([
        'status' => $status,
        'payment_status' => PaymentStatus::Lunas,
        'sumber_pembelian' => 'website',
        'user_id' => null,
    ], $overrides));
}

// ── Guard pembayaran saat proses ──

it('rejects processing an unpaid website order', function (): void {
    $order = orderIn(OrderStatus::MenungguKonfirmasi->value, [
        'payment_status' => PaymentStatus::Menunggu,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.process', $order), [
            'shipping_cost' => 10000,
            'ekspedisi' => 'jne',
            'warehouse_origin' => 'malang',
        ])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::MenungguKonfirmasi);
});

it('allows processing a toko order without payment confirmation', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = orderIn(OrderStatus::MenungguKonfirmasi->value, [
        'payment_status' => PaymentStatus::Menunggu,
        'sumber_pembelian' => 'toko',
    ]);
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => 50000,
        'qty' => 1,
        'price_original' => 50000,
        'price_final' => 50000,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.process', $order), [
            'shipping_cost' => 0,
            'ekspedisi' => 'jne',
            'warehouse_origin' => 'malang',
        ])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Diproses);
});

// ── Auto-batal 24 jam ──

it('cancels unpaid website orders older than 24 hours', function (): void {
    $stale = orderIn(OrderStatus::MenungguKonfirmasi->value, [
        'payment_status' => PaymentStatus::Menunggu,
        'created_at' => now()->subHours(25),
    ]);
    $fresh = orderIn(OrderStatus::MenungguKonfirmasi->value, [
        'payment_status' => PaymentStatus::Menunggu,
        'created_at' => now()->subHours(2),
    ]);
    $paid = orderIn(OrderStatus::MenungguKonfirmasi->value, [
        'created_at' => now()->subHours(25),
    ]);
    $toko = orderIn(OrderStatus::MenungguKonfirmasi->value, [
        'payment_status' => PaymentStatus::Menunggu,
        'sumber_pembelian' => 'toko',
        'created_at' => now()->subHours(25),
    ]);
    $withBukti = orderIn(OrderStatus::MenungguKonfirmasi->value, [
        'payment_status' => PaymentStatus::Menunggu,
        'bukti_transfer_path' => 'bukti-transfer/x.jpg',
        'created_at' => now()->subHours(25),
    ]);

    $this->artisan(CancelUnpaidOrders::class)->assertSuccessful();

    expect($stale->fresh()->status)->toBe(OrderStatus::Batal)
        ->and($fresh->fresh()->status)->toBe(OrderStatus::MenungguKonfirmasi)
        ->and($paid->fresh()->status)->toBe(OrderStatus::MenungguKonfirmasi)
        ->and($toko->fresh()->status)->toBe(OrderStatus::MenungguKonfirmasi)
        ->and($withBukti->fresh()->status)->toBe(OrderStatus::MenungguKonfirmasi);
});

// ── Upload bukti transfer ──

it('stores a transfer proof from the order owner', function (): void {
    $order = orderIn(OrderStatus::MenungguKonfirmasi->value, [
        'payment_status' => PaymentStatus::Menunggu,
        'user_id' => $this->customer->id,
    ]);

    Storage::fake('public');

    $this->actingAs($this->customer)
        ->post(route('my-orders.upload-bukti', $order), [
            'bukti' => UploadedFile::fake()->image('bukti.jpg', 800, 600),
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->bukti_transfer_path)->not->toBeNull()
        ->and($order->bukti_transfer_at)->not->toBeNull()
        ->and(Storage::disk('public')->exists($order->bukti_transfer_path))->toBeTrue();
});

it('hides upload bukti endpoint from strangers', function (): void {
    $order = orderIn(OrderStatus::MenungguKonfirmasi->value, [
        'payment_status' => PaymentStatus::Menunggu,
        'user_id' => $this->customer->id,
    ]);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->post(route('my-orders.upload-bukti', $order), [
            'bukti' => UploadedFile::fake()->image('bukti.jpg'),
        ])
        ->assertNotFound();
});

// ── Booking pengiriman Biteship ──

it('books a shipment and stores AWB + label', function (): void {
    Http::fake([
        'api.biteship.com/v1/orders' => Http::response([
            'success' => true,
            'id' => 'bsh-123',
            'waybill_id' => 'AWB-9999',
            'label_url' => 'https://label.test/awb-9999.pdf',
            'status' => 'confirmed',
        ]),
    ]);

    $order = orderIn(OrderStatus::Diproses->value);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.shipping', $order), [
            'courier' => 'wahana',
            'service' => 'normal',
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->biteship_order_id)->toBe('bsh-123')
        ->and($order->awb)->toBe('AWB-9999')
        ->and($order->biteship_label_url)->toBe('https://label.test/awb-9999.pdf')
        ->and($order->biteship_status)->toBe('confirmed')
        ->and($order->courier_service_code)->toBe('normal');
});

it('rejects booking when order is not processed or unpaid', function (): void {
    $unpaid = orderIn(OrderStatus::Diproses->value, ['payment_status' => PaymentStatus::Menunggu]);
    $toko = orderIn(OrderStatus::Diproses->value, ['sumber_pembelian' => 'toko']);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.shipping', $unpaid), ['courier' => 'wahana'])
        ->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('admin.orders.shipping', $toko), ['courier' => 'wahana'])
        ->assertRedirect();

    expect($unpaid->fresh()->awb)->toBeNull()
        ->and($toko->fresh()->awb)->toBeNull();
});

it('blocks booking with a friendly message when store phone is missing', function (): void {
    Setting::set('store_telepon', null);

    $order = orderIn(OrderStatus::Diproses->value);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.shipping', $order), ['courier' => 'wahana'])
        ->assertRedirect();

    Http::assertNothingSent();

    expect($order->fresh()->awb)->toBeNull()
        ->and(session('inertia.flash_data.toast.message'))->toContain('nomor telepon toko');
});

it('blocks booking with a friendly message when store address is missing', function (): void {
    Setting::set('store_alamat', null);

    $order = orderIn(OrderStatus::Diproses->value);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.shipping', $order), ['courier' => 'wahana'])
        ->assertRedirect();

    Http::assertNothingSent();

    expect($order->fresh()->awb)->toBeNull()
        ->and(session('inertia.flash_data.toast.message'))->toContain('alamat toko');
});

// ── Webhook status ──

it('stores the courier tracking link from the webhook', function (): void {
    $order = orderIn(OrderStatus::Diproses->value, ['biteship_order_id' => 'bsh-777']);

    $payload = [
        'event' => 'order.status',
        'order_id' => 'bsh-777',
        'status' => 'picked',
        'courier_link' => 'https://tracking.example.com/awb-123',
    ];

    $this->postJson(route('webhooks.biteship'), $payload, [
        'X-Signature' => hash_hmac('sha256', json_encode($payload), config('biteship.webhook_secret')),
    ])->assertOk();

    $order->refresh();

    expect($order->biteship_status)->toBe('picked')
        ->and($order->biteship_courier_link)->toBe('https://tracking.example.com/awb-123')
        ->and($order->status)->toBe(OrderStatus::Dikirim);
});

it('marks order as sent on in_transit webhook', function (): void {
    $order = orderIn(OrderStatus::Diproses->value, ['biteship_order_id' => 'bsh-123']);

    $this->postJson(route('webhooks.biteship'), [
        'event' => 'order.status',
        'order_id' => 'bsh-123',
        'status' => 'in_transit',
        'courier_waybill_id' => 'AWB-9999',
    ], ['X-Signature' => hash_hmac('sha256', json_encode([
        'event' => 'order.status',
        'order_id' => 'bsh-123',
        'status' => 'in_transit',
        'courier_waybill_id' => 'AWB-9999',
    ]), config('biteship.webhook_secret'))])
        ->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Dikirim);
});

it('finalizes order with cash flow on delivered webhook', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = orderIn(OrderStatus::Dikirim->value, [
        'biteship_order_id' => 'bsh-456',
        'total' => 62000,
        'shipping_cost' => 12000,
        'warehouse_origin' => 'malang',
    ]);
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => 50000,
        'harga_beli_snapshot' => 30000,
        'qty' => 1,
        'price_original' => 50000,
        'price_final' => 50000,
    ]);

    $this->postJson(route('webhooks.biteship'), [
        'event' => 'order.status',
        'order_id' => 'bsh-456',
        'status' => 'delivered',
    ], ['X-Signature' => hash_hmac('sha256', json_encode([
        'event' => 'order.status',
        'order_id' => 'bsh-456',
        'status' => 'delivered',
    ]), config('biteship.webhook_secret'))])
        ->assertOk();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Selesai)
        ->and($order->cashFlows()->count())->toBe(2);
});

it('rejects webhooks with an invalid signature', function (): void {
    $this->postJson(route('webhooks.biteship'), [
        'event' => 'order.status',
        'order_id' => 'bsh-123',
        'status' => 'delivered',
    ], ['X-Signature' => 'invalid'])
        ->assertForbidden();
});

it('accepts the empty-body installation ping from Biteship', function (): void {
    $this->post(route('webhooks.biteship'), [], ['Content-Type' => 'application/json'])
        ->assertOk()
        ->assertJson(['success' => true]);
});

it('returns 404 for webhooks of unknown orders', function (): void {
    $this->postJson(route('webhooks.biteship'), [
        'event' => 'order.status',
        'order_id' => 'bsh-tidak-ada',
        'status' => 'delivered',
    ], ['X-Signature' => hash_hmac('sha256', json_encode([
        'event' => 'order.status',
        'order_id' => 'bsh-tidak-ada',
        'status' => 'delivered',
    ]), config('biteship.webhook_secret'))])
        ->assertNotFound();
});

it('syncs shipping cost and total on the order.price webhook', function (): void {
    $order = orderIn(OrderStatus::Diproses->value, [
        'biteship_order_id' => 'bsh-555',
        'total' => 62000,
        'shipping_cost' => 12000,
    ]);

    $payload = [
        'event' => 'order.price',
        'order_id' => 'bsh-555',
        'shippment_fee' => 15000,
    ];

    $this->postJson(route('webhooks.biteship'), $payload, [
        'X-Signature' => hash_hmac('sha256', json_encode($payload), config('biteship.webhook_secret')),
    ])->assertOk();

    $order->refresh();

    expect($order->shipping_cost)->toBe(15000)
        ->and($order->total)->toBe(65000)
        ->and(ActivityLog::where('subject_type', $order->getMorphClass())
            ->where('subject_id', $order->id)
            ->where('description', 'like', '%12000 → 15000%')
            ->exists())->toBeTrue();
});

it('logs a warning when the courier cancels the shipment', function (): void {
    $order = orderIn(OrderStatus::Dikirim->value, [
        'biteship_order_id' => 'bsh-888',
    ]);

    $payload = [
        'event' => 'order.status',
        'order_id' => 'bsh-888',
        'status' => 'cancelled',
    ];

    $this->postJson(route('webhooks.biteship'), $payload, [
        'X-Signature' => hash_hmac('sha256', json_encode($payload), config('biteship.webhook_secret')),
    ])->assertOk();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Dikirim)
        ->and($order->biteship_status)->toBe('cancelled')
        ->and(ActivityLog::where('subject_type', $order->getMorphClass())
            ->where('subject_id', $order->id)
            ->where('description', 'like', '%perlu tindakan admin%')
            ->exists())->toBeTrue();
});

it('applies the shipment status when refreshing from the admin panel', function (): void {
    Http::fake([
        'api.biteship.com/*' => Http::response([
            'success' => true,
            'id' => 'bsh-666',
            'status' => 'picked',
        ]),
    ]);

    $order = orderIn(OrderStatus::Diproses->value, [
        'biteship_order_id' => 'bsh-666',
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.shipping.refresh', $order))
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Dikirim);
});

it('charges the price of the courier service chosen at checkout', function (): void {
    Http::fake([
        'api.biteship.com/*' => Http::response([
            'success' => true,
            'pricing' => [
                [
                    'courier_code' => 'jne',
                    'courier_name' => 'JNE',
                    'courier_service_code' => 'reg',
                    'courier_service_name' => 'Reguler',
                    'price' => 12000,
                    'duration' => '1 - 2 days',
                ],
                [
                    'courier_code' => 'jne',
                    'courier_name' => 'JNE',
                    'courier_service_code' => 'yes',
                    'courier_service_name' => 'YES',
                    'price' => 25000,
                    'duration' => '1 day',
                ],
            ],
        ]),
    ]);

    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);

    session(['cart' => [$book->id => 1]]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'nama_pembeli' => 'Pembeli Tester',
        'alamat' => 'Jl. Merdeka 1',
        'provinsi' => 'JAWA TIMUR',
        'kabupaten_kota' => 'KOTA MALANG',
        'kecamatan' => 'KLOJEN',
        'kode_pos' => '65144',
        'metode_bayar' => 'transfer',
        'ekspedisi' => 'jne',
        'courier_service_code' => 'yes',
    ])->assertRedirect();

    $order = Order::firstOrFail();

    expect($order->shipping_cost)->toBe(25000)
        ->and($order->total)->toBe(75000)
        ->and($order->courier_service_code)->toBe('yes');
});

it('rejects checkout when the courier service is unavailable', function (): void {
    Http::fake([
        'api.biteship.com/*' => Http::response([
            'success' => true,
            'pricing' => [
                [
                    'courier_code' => 'jne',
                    'courier_name' => 'JNE',
                    'courier_service_code' => 'reg',
                    'courier_service_name' => 'Reguler',
                    'price' => 12000,
                    'duration' => '1 - 2 days',
                ],
            ],
        ]),
    ]);

    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);

    session(['cart' => [$book->id => 1]]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'nama_pembeli' => 'Pembeli Tester',
        'alamat' => 'Jl. Merdeka 1',
        'provinsi' => 'JAWA TIMUR',
        'kabupaten_kota' => 'KOTA MALANG',
        'kecamatan' => 'KLOJEN',
        'kode_pos' => '65144',
        'metode_bayar' => 'transfer',
        'ekspedisi' => 'jne',
        'courier_service_code' => 'yes',
    ])->assertRedirect();

    expect(Order::count())->toBe(0);
});

// ── Service courier tersimpan ──

it('stores courier service code at checkout', function (): void {
    fakeBiteshipApi();

    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);

    session(['cart' => [$book->id => 1]]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'nama_pembeli' => 'Pembeli Tester',
        'alamat' => 'Jl. Merdeka 1',
        'provinsi' => 'JAWA TIMUR',
        'kabupaten_kota' => 'KOTA MALANG',
        'kecamatan' => 'KLOJEN',
        'kode_pos' => '65144',
        'metode_bayar' => 'transfer',
        'ekspedisi' => 'jne',
        'courier_service_code' => 'reg',
    ])->assertRedirect();

    $order = Order::firstOrFail();

    expect($order->ekspedisi)->toBe('jne')
        ->and($order->courier_service_code)->toBe('reg');
});

it('stores courier service code when processing an order', function (): void {
    $book = Book::factory()->withStock(malang: 5)->create(['aktif' => true, 'harga' => 50000]);
    $order = orderIn(OrderStatus::MenungguKonfirmasi->value);
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => 50000,
        'qty' => 1,
        'price_original' => 50000,
        'price_final' => 50000,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.process', $order), [
            'shipping_cost' => 10000,
            'ekspedisi' => 'jne',
            'courier_service_code' => 'yes',
            'warehouse_origin' => 'malang',
        ])
        ->assertRedirect();

    expect($order->fresh()->courier_service_code)->toBe('yes');
});

// ── Invoice customer ──

it('prints the invoice for the order owner', function (): void {
    $order = orderIn(OrderStatus::Diproses->value, [
        'user_id' => $this->customer->id,
        'no_order' => 'ORD-INV-001',
    ]);

    $this->actingAs($this->customer)
        ->get(route('my-orders.invoice', $order))
        ->assertOk()
        ->assertSee('ORD-INV-001');
});

it('shows the order detail page for the owner with items and bank accounts', function (): void {
    $book = Book::factory()->create(['judul' => 'Buku Detail', 'aktif' => true, 'harga' => 50000]);
    $order = orderIn(OrderStatus::MenungguKonfirmasi->value, [
        'user_id' => $this->customer->id,
        'total' => 62000,
        'shipping_cost' => 12000,
    ]);
    $order->items()->create([
        'book_id' => $book->id,
        'judul_snapshot' => $book->judul,
        'harga_snapshot' => 50000,
        'qty' => 1,
        'price_original' => 50000,
        'price_final' => 50000,
    ]);

    $props = inertiaProps($this->actingAs($this->customer)->get(route('my-orders.show', $order)));

    expect($props['order']['no_order'])->toBe($order->no_order)
        ->and($props['order']['items'])->toHaveCount(1)
        ->and($props['order']['items'][0]['judul_snapshot'])->toBe('Buku Detail')
        ->and($props['bankAccounts'])->toBeArray();
});

it('hides the order detail page from strangers', function (): void {
    $order = orderIn(OrderStatus::Diproses->value, [
        'user_id' => $this->customer->id,
    ]);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('my-orders.show', $order))
        ->assertNotFound();
});

it('hides the invoice from strangers', function (): void {
    $order = orderIn(OrderStatus::Diproses->value, [
        'user_id' => $this->customer->id,
    ]);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('my-orders.invoice', $order))
        ->assertNotFound();
});

// ── Badge activeOrdersCount ──

it('shares active orders count for the logged-in customer', function (): void {
    orderIn(OrderStatus::MenungguKonfirmasi->value, ['user_id' => $this->customer->id]);
    orderIn(OrderStatus::Diproses->value, ['user_id' => $this->customer->id]);
    orderIn(OrderStatus::Selesai->value, ['user_id' => $this->customer->id]);
    orderIn(OrderStatus::Batal->value, ['user_id' => $this->customer->id]);

    $props = inertiaProps($this->actingAs($this->customer)->get(route('my-orders.index')));

    expect($props['activeOrdersCount'] ?? null)->toBe(2);
});

it('shares zero active orders count for guests', function (): void {
    $props = inertiaProps($this->get(route('books.catalog')));

    expect($props['activeOrdersCount'] ?? null)->toBe(0);
});

// ── Guard batal ──

it('blocks cancelling an order with an active Biteship shipment', function (): void {
    $order = orderIn(OrderStatus::Diproses->value, [
        'biteship_order_id' => 'bsh-789',
        'awb' => 'AWB-111',
        'biteship_status' => 'confirmed',
    ]);

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Batal->value])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Diproses);
});

it('allows cancelling after the Biteship shipment is cancelled', function (): void {
    Http::fake([
        'api.biteship.com/*' => Http::response(['success' => true]),
    ]);

    $order = orderIn(OrderStatus::Diproses->value, [
        'biteship_order_id' => 'bsh-789',
        'awb' => 'AWB-111',
        'biteship_status' => 'confirmed',
        'warehouse_origin' => 'malang',
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.orders.shipping.cancel', $order))
        ->assertRedirect();

    expect($order->fresh()->biteship_status)->toBe('cancelled');

    $this->actingAs($this->admin)
        ->patch(route('admin.orders.status', $order), ['status' => OrderStatus::Batal->value])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Batal);
});

it('rejects checkout when the courier is disabled', function (): void {
    Courier::create(['code' => 'wahana', 'name' => 'Wahana', 'is_active' => true, 'sort_order' => 0]);
    Courier::create(['code' => 'jne', 'name' => 'JNE', 'is_active' => false, 'sort_order' => 0]);

    $book = Book::factory()->withStock(malang: 10)->create(['aktif' => true, 'harga' => 50000]);

    session(['cart' => [$book->id => 1]]);

    $this->actingAs($this->customer)->post(route('checkout.store'), [
        'nama_pembeli' => 'Pembeli Tester',
        'alamat' => 'Jl. Merdeka 1',
        'provinsi' => 'JAWA TIMUR',
        'kabupaten_kota' => 'KOTA MALANG',
        'kecamatan' => 'KLOJEN',
        'kode_pos' => '65144',
        'metode_bayar' => 'transfer',
        'ekspedisi' => 'jne',
    ])->assertRedirect()->assertSessionHasErrors('ekspedisi');

    expect(Order::count())->toBe(0);
});
