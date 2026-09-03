<?php

use App\Models\CashFlow;
use App\Models\CashFlowMonth;
use App\Models\KasCategory;
use App\Models\KasSubCategory;
use App\Models\Order;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    // Seed kategori Kas untuk test store/update (OPSI A + kategori)
    $this->kasCategory = KasCategory::create(['nama' => 'Kategori Test '.uniqid(), 'sort_order' => 1]);
    $this->kasSub = KasSubCategory::create(['cash_flow_category_id' => $this->kasCategory->id, 'nama' => 'Sub Test '.uniqid(), 'sort_order' => 1]);
});

it('shows monthly summary rows on the cash recording page', function (): void {
    // OPSI A: Kas hanya manual (income/expense) — revenue/shipping/refund tidak masuk Kas.
    // Pakai tanggal dalam bulan berjalan biar tidak melintasi batas bulan saat
    // test dijalankan tanggal 1–2 (subDays bisa jatuh di bulan lalu).
    $thisMonth = now()->startOfMonth()->toDateString();
    CashFlow::factory()->income(50000)->create([
        'order_id' => null,
        'entry_date' => $thisMonth,
    ]);
    CashFlow::factory()->income(10000)->create([
        'order_id' => null,
        'entry_date' => $thisMonth,
    ]);
    CashFlow::factory()->expense(7500)->create([
        'order_id' => null,
        'entry_date' => $thisMonth,
    ]);
    // Refund lama (order-linked) tidak lagi dihitung di Kas OPSI A.
    CashFlow::factory()->refund(5000)->create([
        'order_id' => Order::factory()->create()->id,
        'entry_date' => now()->subMonth()->toDateString(),
    ]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.kas.index')));

    expect($props['months'])->toHaveCount(1)
        ->and($props['months'][0]['key'])->toBe(now()->format('Y-m'))
        ->and($props['months'][0]['masuk'])->toBe(60000)
        ->and($props['months'][0]['keluar'])->toBe(7500)
        ->and($props['months'][0]['count'])->toBe(3)
        ->and($props['summary']['masuk'])->toBe(60000)
        ->and($props['summary']['keluar'])->toBe(7500);
});

it('shows the month detail with separate in/out tables', function (): void {
    // OPSI A: hanya income/expense manual yang tampil di KasDetail.
    CashFlow::factory()->income(50000)->create([
        'order_id' => null,
        'entry_date' => now()->toDateString(),
    ]);
    CashFlow::factory()->income(20000)->create([
        'order_id' => null,
        'entry_date' => now()->toDateString(),
    ]);
    CashFlow::factory()->expense(7500)->create([
        'order_id' => null,
        'entry_date' => now()->toDateString(),
    ]);
    // Revenue lama tidak tampil di Kas OPSI A.
    CashFlow::factory()->revenue(99999)->create([
        'order_id' => Order::factory()->create()->id,
        'entry_date' => now()->toDateString(),
    ]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.kas.detail', now()->format('Y-m'))));

    expect($props['bulan_label'])->toBeString()
        ->and($props['summary']['masuk'])->toBe(70000)
        ->and($props['summary']['keluar'])->toBe(7500)
        ->and(collect($props['pencatatan'])->pluck('amount')->all())->toBe([50000, 20000])
        ->and(collect($props['pengeluaran'])->pluck('amount')->all())->toBe([7500])
        ->and($props['pencatatan'][0])->toHaveKey('kas_category');
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

it('does not show order-linked cash flows in kas detail (OPSI A: kas mandiri)', function (): void {
    $order = Order::factory()->create();
    $flow = CashFlow::factory()->revenue(25000)->create(['order_id' => $order->id, 'entry_date' => now()->toDateString()]);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.kas.detail', now()->format('Y-m'))));

    $found = collect($props['pencatatan'])->firstWhere('id', $flow->id);

    expect($found)->toBeNull();
});

it('records manual cash in (uang masuk)', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.kas.store'), [
            'flow_type' => 'income',
            'entry_date' => now()->toDateString(),
            'amount' => 150000,
            'description' => 'Pelunasan piutang Budi',
            'kas_category_id' => $this->kasCategory->id,
            'kas_sub_category_id' => $this->kasSub->id,
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
            'kas_category_id' => $this->kasCategory->id,
            'kas_sub_category_id' => $this->kasSub->id,
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

it('creates an empty month and shows it in the cash recording list', function (): void {
    $bulan = '2026-09';

    $this->actingAs($this->admin)
        ->post(route('admin.kas.months.store'), ['bulan' => $bulan])
        ->assertRedirect(route('admin.kas.detail', $bulan));

    expect(CashFlowMonth::where('bulan', $bulan)->exists())->toBeTrue();

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.kas.index')));

    expect($props['months'][0])->toMatchArray([
        'key' => $bulan,
        'count' => 0,
        'masuk' => 0,
        'keluar' => 0,
    ]);
});

it('blocks opening a month that already has cash entries', function (): void {
    CashFlow::factory()->expense(5000)->create([
        'entry_date' => '2026-08-15',
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.kas.months.store'), ['bulan' => '2026-08'])
        ->assertRedirect();

    expect(CashFlowMonth::where('bulan', '2026-08')->exists())->toBeFalse();
});

it('blocks opening the same month twice', function (): void {
    CashFlowMonth::create(['bulan' => '2026-07']);

    $this->actingAs($this->admin)
        ->post(route('admin.kas.months.store'), ['bulan' => '2026-07'])
        ->assertRedirect();

    expect(CashFlowMonth::where('bulan', '2026-07')->count())->toBe(1);
});

it('validates the month format', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.kas.months.store'), ['bulan' => 'bukan-bulan'])
        ->assertSessionHasErrors('bulan');

    expect(CashFlowMonth::count())->toBe(0);
});

it('includes manually opened months in the report filter options', function (): void {
    CashFlowMonth::create(['bulan' => '2026-06']);

    $props = inertiaProps($this->actingAs($this->admin)
        ->get(route('admin.kas.laporan')));

    expect(collect($props['monthOptions'])->pluck('value'))->toContain('2026-06');
});

it('allows editing manual entry before month is closed', function (): void {
    $flow = CashFlow::factory()->income(100000)->create([
        'order_id' => null,
        'entry_date' => '2026-08-10',
        'description' => 'Awal',
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.kas.update', $flow), [
            'flow_type' => 'income',
            'entry_date' => '2026-08-15',
            'amount' => 150000,
            'description' => 'Revisi',
            'kas_category_id' => $this->kasCategory->id,
            'kas_sub_category_id' => $this->kasSub->id,
        ])
        ->assertRedirect();

    expect($flow->fresh()->amount)->toBe(150000)
        ->and($flow->fresh()->description)->toBe('Revisi');
});

it('blocks editing when month is closed', function (): void {
    $flow = CashFlow::factory()->income(50000)->create(['order_id' => null, 'entry_date' => '2026-07-10']);
    CashFlowMonth::create(['bulan' => '2026-07', 'is_closed' => true, 'closed_at' => now()]);

    $this->actingAs($this->admin)
        ->put(route('admin.kas.update', $flow), [
            'flow_type' => 'income',
            'entry_date' => '2026-07-15',
            'amount' => 99999,
            'description' => 'Coba edit',
            'kas_category_id' => $this->kasCategory->id,
            'kas_sub_category_id' => $this->kasSub->id,
        ])
        ->assertRedirect();

    expect($flow->fresh()->amount)->toBe(50000);
});

it('blocks editing order-linked cash flows', function (): void {
    $order = Order::factory()->create();
    $flow = CashFlow::factory()->revenue(25000)->create(['order_id' => $order->id, 'entry_date' => now()->toDateString()]);

    $this->actingAs($this->admin)
        ->put(route('admin.kas.update', $flow), [
            'flow_type' => 'income',
            'entry_date' => now()->toDateString(),
            'amount' => 99999,
            'description' => 'Coba edit',
        ])
        ->assertRedirect();

    expect($flow->fresh()->amount)->toBe(25000);
});

it('blocks creating entry when month is closed', function (): void {
    CashFlowMonth::create(['bulan' => '2026-09', 'is_closed' => true, 'closed_at' => now(), 'closed_by' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->post(route('admin.kas.store'), [
            'flow_type' => 'income',
            'entry_date' => '2026-09-15',
            'amount' => 50000,
            'description' => 'Coba',
            'kas_category_id' => $this->kasCategory->id,
            'kas_sub_category_id' => $this->kasSub->id,
        ])
        ->assertRedirect();

    expect(CashFlow::where('amount', 50000)->exists())->toBeFalse();
});

it('can close and reopen a month', function (): void {
    $bulan = '2026-10';
    CashFlowMonth::create(['bulan' => $bulan, 'is_closed' => false]);

    $this->actingAs($this->admin)
        ->post(route('admin.kas.close', $bulan))
        ->assertRedirect();

    expect(CashFlowMonth::where('bulan', $bulan)->first()->is_closed)->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('admin.kas.reopen', $bulan))
        ->assertRedirect();

    expect(CashFlowMonth::where('bulan', $bulan)->first()->is_closed)->toBeFalse();
});

it('creates close record for implicit month (only cash flows)', function (): void {
    CashFlow::factory()->income(10000)->create(['order_id' => null, 'entry_date' => '2026-11-05']);

    $this->actingAs($this->admin)
        ->post(route('admin.kas.close', '2026-11'))
        ->assertRedirect();

    expect(CashFlowMonth::where('bulan', '2026-11')->first()->is_closed)->toBeTrue();
});

it('allows deleting manual entry before month is closed', function (): void {
    $flow = CashFlow::factory()->income(75000)->create(['order_id' => null, 'entry_date' => '2026-08-12', 'kas_category_id' => $this->kasCategory->id, 'kas_sub_category_id' => $this->kasSub->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.kas.destroy', $flow))
        ->assertRedirect();

    expect(CashFlow::where('id', $flow->id)->exists())->toBeFalse();
});

it('blocks deleting when month is closed', function (): void {
    $flow = CashFlow::factory()->expense(40000)->create(['order_id' => null, 'entry_date' => '2026-07-12', 'kas_category_id' => $this->kasCategory->id, 'kas_sub_category_id' => $this->kasSub->id]);
    CashFlowMonth::create(['bulan' => '2026-07', 'is_closed' => true, 'closed_at' => now()]);

    $this->actingAs($this->admin)
        ->delete(route('admin.kas.destroy', $flow))
        ->assertRedirect();

    expect(CashFlow::where('id', $flow->id)->exists())->toBeTrue();
});

it('blocks deleting order-linked cash flows', function (): void {
    $order = Order::factory()->create();
    $flow = CashFlow::factory()->revenue(25000)->create(['order_id' => $order->id, 'entry_date' => now()->toDateString()]);

    $this->actingAs($this->admin)
        ->delete(route('admin.kas.destroy', $flow))
        ->assertRedirect();

    expect(CashFlow::where('id', $flow->id)->exists())->toBeTrue();
});
