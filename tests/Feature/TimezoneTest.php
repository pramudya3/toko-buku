<?php

use App\Models\Order;
use App\Models\Promotion;

it('configures app timezone Asia/Jakarta and DB session Asia/Jakarta', function (): void {
    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(now()->timezoneName)->toBe('Asia/Jakarta')
        ->and(config('database.connections.pgsql.timezone'))->toBe('Asia/Jakarta');
});

it('round-trips model timestamps in the app timezone', function (): void {
    $order = Order::factory()->create();

    $fresh = $order->fresh();

    expect($fresh->created_at->timezoneName)->toBe('Asia/Jakarta')
        ->and($fresh->created_at->isSameDay(now()))->toBeTrue();
});

it('serializes date-only casts without timezone shift (WIB → UTC ISO)', function (): void {
    $start = now()->toDateString();
    $end = now()->addDay()->toDateString();

    $promo = Promotion::factory()->create([
        'start_date' => $start,
        'end_date' => $end,
    ]);

    // date:Y-m-d — tetap tanggal murni, tidak geser -1 hari karena serialisasi UTC.
    expect($promo->toArray()['start_date'])->toBe($start)
        ->and($promo->toArray()['end_date'])->toBe($end);
});

it('serializes datetime casts as absolute instants (UTC ISO)', function (): void {
    $order = Order::factory()->create();

    $serialized = $order->toArray()['created_at'];

    expect($serialized)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/');
});
