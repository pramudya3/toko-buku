<?php

use App\Enums\FlowType;
use App\Models\CashFlow;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('lists cash flows read-only with date filter and summary (CF-05)', function (): void {
    $order = \App\Models\Order::factory()->create();

    CashFlow::factory()->revenue(50000)->create([
        'order_id' => $order->id,
        'entry_date' => now()->subDays(2)->toDateString(),
    ]);
    CashFlow::factory()->shipping(10000)->create([
        'order_id' => $order->id,
        'entry_date' => now()->subDays(2)->toDateString(),
    ]);
    CashFlow::factory()->refund(5000)->create([
        'order_id' => $order->id,
        'entry_date' => now()->subDays(30)->toDateString(),
    ]);

    $from = now()->subDays(7)->toDateString();
    $to = now()->toDateString();

    $this->actingAs($this->admin)
        ->get(route('admin.cash-flow.index', ['from' => $from, 'to' => $to]))
        ->assertSuccessful();

    // Ringkasan: inflow 60000, outflow 0 (refund di luar rentang).
    $props = inertiaProps($this->get(route('admin.cash-flow.index', ['from' => $from, 'to' => $to])));

    expect($props['summary']['inflow'])->toBe(60000)
        ->and($props['summary']['outflow'])->toBe(0);
});

it('includes refunds as outflow in the summary', function (): void {
    $order = \App\Models\Order::factory()->create();
    CashFlow::factory()->refund(5000)->create(['order_id' => $order->id]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.cash-flow.index')));

    expect($props['summary']['outflow'])->toBe(5000);
});

it('does not expose write routes for cash flows (CF-02)', function (): void {
    $this->actingAs($this->admin)
        ->post('/admin/cash-flow', ['amount' => 100])
        ->assertMethodNotAllowed();
});

it('links cash flows to their order number (auditability, AC-07)', function (): void {
    $order = \App\Models\Order::factory()->create();
    $flow = CashFlow::factory()->revenue(25000)->create(['order_id' => $order->id]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.cash-flow.index')));

    $first = collect($props['flows']['data'])->firstWhere('id', $flow->id);

    expect($first['order']['no_order'])->toBe($order->no_order);
});
