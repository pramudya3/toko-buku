<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Pesanan Saya storefront paralel /pcd/pesanan-saya — memakai backend yang
 * sama dengan my-orders utama, hanya komponen Inertia yang berbeda.
 */
beforeEach(function (): void {
    $this->customer = User::factory()->create();
});

it('redirects guests to login on pcd my-orders', function (): void {
    $this->get(route('pcd.my-orders.index'))
        ->assertRedirect(route('login'));
});

it('renders the pcd my-orders page with only the customer orders', function (): void {
    $mine = Order::factory()->create(['user_id' => $this->customer->id]);
    Order::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->actingAs($this->customer)
        ->get(route('pcd.my-orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront-pcd/MyOrders')
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $mine->id));
});

it('shows the pcd order detail for the owner', function (): void {
    $order = Order::factory()->create([
        'user_id' => $this->customer->id,
        'status' => OrderStatus::Diproses->value,
    ]);

    $this->actingAs($this->customer)
        ->get(route('pcd.my-orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront-pcd/OrderDetail')
            ->where('order.id', $order->id)
            ->has('bankAccounts'));
});

it('hides the pcd order detail from strangers', function (): void {
    $order = Order::factory()->create([
        'user_id' => $this->customer->id,
    ]);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('pcd.my-orders.show', $order))
        ->assertNotFound();
});

it('prints the pcd invoice for the owner', function (): void {
    $order = Order::factory()->create([
        'user_id' => $this->customer->id,
    ]);

    $this->actingAs($this->customer)
        ->get(route('pcd.my-orders.invoice', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('print/orders/Invoice'));
});

it('stores a transfer proof via the pcd route', function (): void {
    $order = Order::factory()->create([
        'user_id' => $this->customer->id,
        'payment_status' => PaymentStatus::Menunggu,
    ]);

    Storage::fake('public');

    $this->actingAs($this->customer)
        ->post(route('pcd.my-orders.upload-bukti', $order), [
            'bukti' => UploadedFile::fake()->image('bukti.jpg', 800, 600),
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->bukti_transfer_path)->not->toBeNull()
        ->and($order->bukti_transfer_at)->not->toBeNull()
        ->and(Storage::disk('public')->exists($order->bukti_transfer_path))->toBeTrue();
});
