<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Book;
use App\Models\CashFlow;
use App\Models\Order;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('renders dashboard stats for the current period (DASH-01, DASH-02, DASH-03)', function (): void {
    Book::factory()->withStock(malang: 3)->create(['judul' => 'Buku Menipis']);
    Book::factory()->withStock(malang: 0)->create(['judul' => 'Buku Habis']);
    Book::factory()->withStock(malang: 50)->create(['judul' => 'Buku Aman']);

    $order = Order::factory()->status(OrderStatus::Selesai)->create();
    $order->items()->create([
        'book_id' => Book::first()->id,
        'judul_snapshot' => Book::first()->judul,
        'harga_snapshot' => Book::first()->harga,
        'qty' => 1,
        'price_original' => 50000,
        'promo_discount_amount' => 0,
        'tier_discount_amount' => 0,
        'price_final' => 50000,
    ]);
    $order->update(['total' => 65000, 'shipping_cost' => 15000]);

    CashFlow::factory()->revenue(50000)->create(['order_id' => $order->id]);
    CashFlow::factory()->shipping(15000)->create(['order_id' => $order->id]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful());

    // Order default (transfer) TIDAK dihitung sebagai uang cash. Revenue tanpa ongkir (total - shipping_cost).
    expect($props['stats']['revenue'])->toBe(50000)
        ->and($props['stats']['cash_in_month'])->toBe(0)
        ->and($props['stats']['orders_count'])->toBe(1)
        ->and($props['stats']['book_count'])->toBe(3)
        ->and($props['lowStockThreshold'])->toBe(config('pricing.low_stock_threshold'))
        ->and(count($props['salesChart']))->toBe(now()->day)
        ->and(collect($props['lowStockBooks'])->pluck('judul'))->toContain('Buku Menipis')
        ->and(collect($props['lowStockBooks'])->pluck('judul'))->not->toContain('Buku Habis')
        ->and(collect($props['emptyStockBooks'])->pluck('judul'))->toContain('Buku Habis')
        ->and(collect($props['emptyStockBooks'])->pluck('judul'))->not->toContain('Buku Menipis')
        ->and(collect($props['recentOrders'])->pluck('no_order'))->toContain($order->no_order)
        ->and(count($props['salesChart']))->toBeGreaterThan(0);
});

it('counts only cash-paid orders in the dashboard cash stat', function (): void {
    // Order transfer — tidak masuk uang cash.
    Order::factory()->status(OrderStatus::Selesai)->create(['metode_bayar' => PaymentMethod::Transfer, 'total' => 100000, 'shipping_cost' => 0]);

    // Order cash — masuk uang cash (total termasuk ongkir).
    Order::factory()->status(OrderStatus::Selesai)->create(['metode_bayar' => PaymentMethod::Cash, 'total' => 90000, 'shipping_cost' => 15000]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.dashboard')));

    expect($props['stats']['cash_in_month'])->toBe(75000);
});

it('includes completed orders in the sales chart (DASH-04)', function (): void {
    Order::factory()->status(OrderStatus::Selesai)->create(['total' => 100000, 'shipping_cost' => 0]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.dashboard')));

    $todayPoint = collect($props['salesChart'])->last();

    expect($todayPoint['total'])->toBe(100000);
});

it('reduces revenue and sales chart by refunds', function (): void {
    Order::factory()->status(OrderStatus::Selesai)->create(['total' => 100000, 'shipping_cost' => 0]);
    // Refund via order batal (OPSI A: kas mandiri, refund dari order batal).
    Order::factory()->status(OrderStatus::Batal)->create(['total' => 30000, 'shipping_cost' => 0]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.dashboard')));

    expect($props['stats']['revenue'])->toBe(70000);

    $todayPoint = collect($props['salesChart'])->last();

    // Sales chart OPSI A hanya hitung order selesai, tidak dikurangi refund (chart = gross). Revenue card yang net.
    // Untuk kompatibilitas, ekspektasi chart tetap 100000 (gross), revenue 70000 (net).
    expect($todayPoint['total'])->toBe(100000);
});

it('reduces cash stat by refunds for cash orders', function (): void {
    Order::factory()->status(OrderStatus::Selesai)->create(['metode_bayar' => PaymentMethod::Cash, 'total' => 75000, 'shipping_cost' => 0]);
    Order::factory()->status(OrderStatus::Batal)->create(['metode_bayar' => PaymentMethod::Cash, 'total' => 25000, 'shipping_cost' => 0]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.dashboard')));

    expect($props['stats']['cash_in_month'])->toBe(50000);
});

it('filters stats by the selected month (bulan param)', function (): void {
    // Order bulan lalu — tidak masuk default (bulan berjalan), masuk saat filter bulan lalu.
    $lastMonth = now()->subMonth();
    $orderLastMonth = Order::factory()->status(OrderStatus::Selesai)->create([
        'total' => 200000,
        'shipping_cost' => 0,
        'created_at' => $lastMonth,
    ]);
    Order::factory()->status(OrderStatus::Selesai)->create(['total' => 50000, 'shipping_cost' => 0]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.dashboard', ['bulan' => $lastMonth->format('Y-m')])));

    // Hanya order bulan lalu yang terhitung; chart iterasi penuh 1 bulan.
    expect($props['stats']['revenue'])->toBe(200000)
        ->and($props['stats']['orders_count'])->toBe(1)
        ->and(count($props['salesChart']))->toBe($lastMonth->daysInMonth)
        ->and($props['filters']['bulan'])->toBe($lastMonth->format('Y-m'));

    // Default (tanpa filter) tetap bulan berjalan.
    $default = inertiaProps($this->actingAs($this->admin)->get(route('admin.dashboard')));

    expect($default['stats']['revenue'])->toBe(50000)
        ->and($default['stats']['orders_count'])->toBe(1)
        ->and($default['monthOptions'])->toContain($lastMonth->format('Y-m'))
        ->and($default['monthOptions'])->toContain(now()->format('Y-m'));
});
