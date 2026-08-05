<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('lists dropship orders only (DROP-02)', function (): void {
    $dropship = Order::factory()->dropship()->create();
    Order::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.dropship.index'))
        ->assertSuccessful()
        ->assertSee($dropship->no_order);
});

it('filters dropship report by status and date range (DROP-03)', function (): void {
    $selesai = Order::factory()->dropship()->status(OrderStatus::Selesai)->create();
    $batal = Order::factory()->dropship()->status(OrderStatus::Batal)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.dropship.index', ['status' => OrderStatus::Selesai->value]))
        ->assertSee($selesai->no_order)
        ->assertDontSee($batal->no_order);
});

it('shows end-customer data on the order detail (DROP-01)', function (): void {
    $order = Order::factory()->dropship()->create();
    $order->dropshipper()->create([
        'user_id' => $order->user_id,
        'end_customer_name' => 'End Customer Test',
        'end_customer_whatsapp' => '081111111111',
        'end_customer_address' => 'Jl. Test No. 1',
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.show', $order))
        ->assertSuccessful()
        ->assertSee('End Customer Test');
});
