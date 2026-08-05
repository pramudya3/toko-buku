<?php

use App\Enums\OrderStatus;
use App\Models\Book;
use App\Models\CashFlow;
use App\Models\Order;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('renders dashboard stats for the current period (DASH-01, DASH-02, DASH-03)', function (): void {
    Book::factory()->withStock(malang: 3)->create(['judul' => 'Buku Menipis']);
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

    expect($props['stats']['revenue'])->toBe(50000)
        ->and($props['stats']['cash_in_month'])->toBe(65000)
        ->and($props['stats']['orders_count'])->toBe(1)
        ->and($props['stats']['book_count'])->toBe(2)
        ->and(collect($props['lowStockBooks'])->pluck('judul'))->toContain('Buku Menipis')
        ->and(collect($props['recentOrders'])->pluck('no_order'))->toContain($order->no_order)
        ->and(count($props['salesChart']))->toBeGreaterThan(0);
});

it('includes completed orders in the sales chart (DASH-04)', function (): void {
    $order = Order::factory()->status(OrderStatus::Selesai)->create();
    CashFlow::factory()->revenue(100000)->create(['order_id' => $order->id]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.dashboard')));

    $todayPoint = collect($props['salesChart'])->last();

    expect($todayPoint['total'])->toBe(100000);
});
