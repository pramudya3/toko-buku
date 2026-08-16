<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusNotification;
use App\Services\OrderStatusService;
use Illuminate\Notifications\DatabaseNotification;

it('notifies the order owner when status changes', function (): void {
    $customer = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'status' => OrderStatus::MenungguKonfirmasi,
    ]);

    app(OrderStatusService::class)->transition($order, OrderStatus::Diproses);

    $notification = $customer->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->type)->toBe(OrderStatusNotification::class)
        ->and($notification->data['message'])->toContain($order->no_order);
});

it('does not notify orders without an owner', function (): void {
    $order = Order::factory()->create([
        'user_id' => null,
        'status' => OrderStatus::MenungguKonfirmasi,
    ]);

    app(OrderStatusService::class)->transition($order, OrderStatus::Diproses);

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('only lets the owner mark a notification as read', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $owner->id]);
    $order->user->notify(new OrderStatusNotification($order, OrderStatus::Diproses));

    $notification = $owner->notifications()->first();

    $this->actingAs($other)
        ->post(route('notifications.read', $notification))
        ->assertNotFound();

    $this->actingAs($owner)
        ->post(route('notifications.read', $notification))
        ->assertRedirect();

    expect($owner->unreadNotifications()->count())->toBe(0);
});

it('marks all notifications as read', function (): void {
    $owner = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $owner->id]);
    $order->user->notify(new OrderStatusNotification($order, OrderStatus::Diproses));
    $order->user->notify(new OrderStatusNotification($order, OrderStatus::Dikirim));

    $this->actingAs($owner)
        ->post(route('notifications.read-all'))
        ->assertRedirect();

    expect($owner->unreadNotifications()->count())->toBe(0);
});
