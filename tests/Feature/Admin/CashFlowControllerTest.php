<?php

use App\Models\CashFlow;
use App\Models\Order;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('shows monthly summary rows on the cash recording page', function (): void {
    $order = Order::factory()->create();

    CashFlow::factory()->revenue(50000)->create([
        'order_id' => $order->id,
        'entry_date' => now()->subDays(2)->toDateString(),
    ]);
    CashFlow::factory()->shipping(10000)->create([
        'order_id' => $order->id,
        'entry_date' => now()->subDays(2)->toDateString(),
    ]);
    CashFlow::factory()->expense(7500)->create([
        'entry_date' => now()->subDays(3)->toDateString(),
    ]);
    CashFlow::factory()->refund(5000)->create([
        'entry_date' => now()->subMonth()->toDateString(),
    ]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.kas.index')));

    expect($props['months'])->toHaveCount(2)
        ->and($props['months'][0]['key'])->toBe(now()->format('Y-m'))
        ->and($props['months'][0]['masuk'])->toBe(60000)
        ->and($props['months'][0]['keluar'])->toBe(7500)
        ->and($props['months'][0]['count'])->toBe(3)
        ->and($props['months'][1]['masuk'])->toBe(0)
        ->and($props['months'][1]['keluar'])->toBe(5000)
        ->and($props['summary']['masuk'])->toBe(60000)
        ->and($props['summary']['keluar'])->toBe(12500);
});

it('shows the month detail with separate in/out tables', function (): void {
    $order = Order::factory()->create();

    CashFlow::factory()->revenue(50000)->create([
        'order_id' => $order->id,
        'entry_date' => now()->toDateString(),
    ]);
    CashFlow::factory()->income(20000)->create([
        'entry_date' => now()->toDateString(),
    ]);
    CashFlow::factory()->expense(7500)->create([
        'entry_date' => now()->toDateString(),
    ]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.kas.detail', now()->format('Y-m'))));

    expect($props['bulan_label'])->toBeString()
        ->and($props['summary']['masuk'])->toBe(70000)
        ->and($props['summary']['keluar'])->toBe(7500)
        ->and(collect($props['pencatatan'])->pluck('amount')->all())->toBe([50000, 20000])
        ->and(collect($props['pengeluaran'])->pluck('amount')->all())->toBe([7500])
        ->and($props['pencatatan'][0]['order_no'])->toBe($order->no_order);
});

it('returns 404 for invalid or empty month details', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.kas.detail', '2026-13'))
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->get(route('admin.kas.detail', '2020-01'))
        ->assertOk() // bulan valid tapi kosong → halaman tetap tampil
        ->assertInertia(fn ($page) => $page
            ->component('admin/kas/KasDetail')
            ->where('pencatatan', [])
            ->where('pengeluaran', []));
});

it('filters the cash report by month or all time', function (): void {
    CashFlow::factory()->income(100000)->create(['entry_date' => now()->toDateString()]);
    CashFlow::factory()->income(50000)->create(['entry_date' => now()->subMonth()->toDateString()]);

    // Bulan berjalan — hanya 100000.
    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.kas.laporan', ['bulan' => now()->format('Y-m')])));

    expect($props['summary']['inflow'])->toBe(100000)
        ->and($props['filters']['bulan'])->toBe(now()->format('Y-m'));

    // Keseluruhan — 150000.
    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.kas.laporan')));

    expect($props['summary']['inflow'])->toBe(150000)
        ->and($props['filters']['bulan'])->toBe('')
        ->and($props['monthOptions'])->toHaveCount(2);
});

it('allows manual cash entries but rejects invalid flow types (CF-02)', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.kas.store'), ['amount' => 100])
        ->assertSessionHasErrors(['flow_type', 'entry_date', 'description']);

    $this->actingAs($this->admin)
        ->post(route('admin.kas.store'), [
            'flow_type' => 'hacking',
            'entry_date' => now()->toDateString(),
            'amount' => 100,
            'description' => 'x',
        ])
        ->assertSessionHasErrors('flow_type');
});

it('links cash flows to their order number (auditability, AC-07)', function (): void {
    $order = Order::factory()->create();
    $flow = CashFlow::factory()->revenue(25000)->create(['order_id' => $order->id]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.kas.detail', now()->format('Y-m'))));

    $first = collect($props['pencatatan'])->firstWhere('id', $flow->id);

    expect($first['order_no'])->toBe($order->no_order);
});

it('records manual cash in (uang masuk)', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.kas.store'), [
            'flow_type' => 'income',
            'entry_date' => now()->toDateString(),
            'amount' => 150000,
            'description' => 'Pelunasan piutang Budi',
        ])
        ->assertRedirect();

    expect(CashFlow::where('flow_type', 'income')->where('amount', 150000)->exists())->toBeTrue();
});

it('records manual cash out (uang keluar)', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.kas.store'), [
            'flow_type' => 'expense',
            'entry_date' => now()->toDateString(),
            'amount' => 75000,
            'description' => 'Beli plastik packing',
        ])
        ->assertRedirect();

    expect(CashFlow::where('flow_type', 'expense')->where('amount', 75000)->exists())->toBeTrue();
});

it('includes manual entries in the cash report summary', function (): void {
    CashFlow::create(['order_id' => null, 'entry_date' => now()->toDateString(), 'flow_type' => 'income', 'amount' => 100000, 'description' => 'Tunai toko']);
    CashFlow::create(['order_id' => null, 'entry_date' => now()->toDateString(), 'flow_type' => 'expense', 'amount' => 40000, 'description' => 'Operasional']);

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.kas.laporan')));

    expect($props['summary']['inflow'])->toBe(100000)
        ->and($props['summary']['outflow'])->toBe(40000)
        ->and($props['summary']['net'])->toBe(60000);
});

it('validates manual cash entries', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.kas.store'), [
            'flow_type' => 'income',
            'entry_date' => now()->toDateString(),
            'amount' => 0,
            'description' => '',
        ])
        ->assertSessionHasErrors(['amount', 'description']);

    $this->actingAs($this->admin)
        ->post(route('admin.kas.store'), [
            'flow_type' => 'sembarang',
            'entry_date' => now()->toDateString(),
            'amount' => 1000,
            'description' => 'x',
        ])
        ->assertSessionHasErrors('flow_type');
});
