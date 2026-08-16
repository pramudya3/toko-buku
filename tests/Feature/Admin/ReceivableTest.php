<?php

use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
    $this->customer = User::factory()->customer()->create();
});

it('lists receivables with remaining balance', function (): void {
    $receivable = Receivable::create([
        'customer_id' => $this->customer->id,
        'amount' => 100000,
        'notes' => 'DP 50rb',
    ]);

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.receivables.index')));

    expect($props['receivables']['total'])->toBe(1)
        ->and($props['receivables']['data'][0]['amount'])->toBe(100000)
        ->and($props['receivables']['data'][0]['paid_amount'])->toBe(0)
        ->and($props['receivables']['data'][0]['customer']['name'])->toBe($this->customer->name);
});

it('creates a receivable', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.receivables.store'), [
            'customer_id' => $this->customer->id,
            'amount' => 250000,
            'due_date' => '2026-09-01',
            'notes' => 'Belum transfer',
        ])
        ->assertRedirect();

    $receivable = Receivable::where('customer_id', $this->customer->id)->first();

    expect($receivable)->not->toBeNull()
        ->and($receivable->amount)->toBe(250000)
        ->and($receivable->due_date->toDateString())->toBe('2026-09-01');
});

it('validates receivable amount', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.receivables.store'), [
            'customer_id' => $this->customer->id,
            'amount' => 0,
        ])
        ->assertSessionHasErrors('amount');

    $this->actingAs($this->admin)
        ->post(route('admin.receivables.store'), ['amount' => 50000])
        ->assertSessionHasErrors('customer_id');
});

it('records partial payment and updates remaining balance', function (): void {
    $receivable = Receivable::create([
        'customer_id' => $this->customer->id,
        'amount' => 100000,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.receivables.payments.store', $receivable), [
            'amount' => 40000,
            'paid_at' => '2026-08-10',
            'metode' => 'transfer',
        ])
        ->assertRedirect();

    $receivable->refresh();

    expect($receivable->paid_amount)->toBe(40000)
        ->and($receivable->remaining())->toBe(60000)
        ->and($receivable->payments()->count())->toBe(1)
        ->and($receivable->payments()->first()->metode)->toBe('transfer');
});

it('marks receivable as lunas when fully paid', function (): void {
    $receivable = Receivable::create([
        'customer_id' => $this->customer->id,
        'amount' => 100000,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.receivables.payments.store', $receivable), [
            'amount' => 40000,
            'paid_at' => '2026-08-10',
            'metode' => 'cash',
        ]);

    $this->actingAs($this->admin)
        ->post(route('admin.receivables.payments.store', $receivable), [
            'amount' => 60000,
            'paid_at' => '2026-08-15',
            'metode' => 'transfer',
        ]);

    $receivable->refresh();

    expect($receivable->paid_amount)->toBe(100000)
        ->and($receivable->remaining())->toBe(0)
        ->and($receivable->isPaidOff())->toBeTrue()
        ->and($receivable->payments()->count())->toBe(2);
});

it('rejects payment exceeding the remaining balance', function (): void {
    $receivable = Receivable::create([
        'customer_id' => $this->customer->id,
        'amount' => 50000,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.receivables.payments.store', $receivable), [
            'amount' => 75000,
            'paid_at' => '2026-08-10',
            'metode' => 'transfer',
        ])
        ->assertSessionHasErrors('amount');

    expect($receivable->fresh()->paid_amount)->toBe(0);
});

it('filters receivables by status', function (): void {
    Receivable::create(['customer_id' => $this->customer->id, 'amount' => 50000]);
    $lunas = Receivable::create(['customer_id' => $this->customer->id, 'amount' => 30000, 'paid_amount' => 30000]);

    $props = inertiaProps($this->actingAs($this->admin)->get(route('admin.receivables.index', ['status' => 'lunas'])));

    expect($props['receivables']['total'])->toBe(1)
        ->and($props['receivables']['data'][0]['id'])->toBe($lunas->id);
});

it('prevents deleting a receivable with payments', function (): void {
    $receivable = Receivable::create(['customer_id' => $this->customer->id, 'amount' => 50000]);
    ReceivablePayment::create([
        'receivable_id' => $receivable->id,
        'paid_at' => '2026-08-10',
        'amount' => 50000,
        'metode' => 'cash',
    ]);

    $this->actingAs($this->admin)
        ->delete(route('admin.receivables.destroy', $receivable))
        ->assertRedirect();

    expect(Receivable::find($receivable->id))->not->toBeNull()
        ->and(session('inertia.flash_data.toast.type'))->toBe('error');
});

it('deletes a receivable without payments', function (): void {
    $receivable = Receivable::create(['customer_id' => $this->customer->id, 'amount' => 50000]);

    $this->actingAs($this->admin)
        ->delete(route('admin.receivables.destroy', $receivable))
        ->assertRedirect();

    expect(Receivable::find($receivable->id))->toBeNull();
});
