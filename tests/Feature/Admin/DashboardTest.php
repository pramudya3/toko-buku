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

    // Order default (transfer) TIDAK dihitung sebagai uang cash.
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
    $transferOrder = Order::factory()->status(OrderStatus::Selesai)->create(['metode_bayar' => PaymentMethod::Transfer]);
    CashFlow::factory()->revenue(100000)->create(['order_id' => $transferOrder->id]);

    // Order cash — masuk uang cash (revenue + ongkir).
    $cashOrder = Order::factory()->status(OrderStatus::Selesai)->create(['metode_bayar' => PaymentMethod::Cash]);
    CashFlow::factory()->revenue(75000)->create(['order_id' => $cashOrder->id]);
    CashFlow::factory()->shipping(15000)->create(['order_id' => $cashOrder->id]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.dashboard')));

    expect($props['stats']['cash_in_month'])->toBe(90000);
});

it('includes completed orders in the sales chart (DASH-04)', function (): void {
    $order = Order::factory()->status(OrderStatus::Selesai)->create();
    CashFlow::factory()->revenue(100000)->create(['order_id' => $order->id]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.dashboard')));

    $todayPoint = collect($props['salesChart'])->last();

    expect($todayPoint['total'])->toBe(100000);
});

it('reduces revenue and sales chart by refunds', function (): void {
    $order = Order::factory()->status(OrderStatus::Selesai)->create();
    CashFlow::factory()->revenue(100000)->create(['order_id' => $order->id]);
    CashFlow::factory()->refund(30000)->create(['order_id' => $order->id]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.dashboard')));

    expect($props['stats']['revenue'])->toBe(70000);

    $todayPoint = collect($props['salesChart'])->last();

    expect($todayPoint['total'])->toBe(70000);
});

it('reduces cash stat by refunds for cash orders', function (): void {
    $order = Order::factory()->status(OrderStatus::Selesai)->create(['metode_bayar' => PaymentMethod::Cash]);
    CashFlow::factory()->revenue(75000)->create(['order_id' => $order->id]);
    CashFlow::factory()->refund(25000)->create(['order_id' => $order->id]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.dashboard')));

    expect($props['stats']['cash_in_month'])->toBe(50000);
});
