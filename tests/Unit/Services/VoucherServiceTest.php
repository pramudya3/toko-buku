<?php

use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherUsage;
use App\Services\VoucherService;

it('computes percentage voucher discount from subtotal', function (): void {
    $voucher = Voucher::factory()->percentage(10)->create();

    expect(app(VoucherService::class)->discountAmount($voucher, 250000))->toBe(25000);
});

it('computes fixed voucher discount capped at subtotal', function (): void {
    $voucher = Voucher::factory()->fixed(30000)->create();

    expect(app(VoucherService::class)->discountAmount($voucher, 100000))->toBe(30000);
});

it('caps fixed voucher discount when subtotal is smaller', function (): void {
    $voucher = Voucher::factory()->fixed(30000)->create();

    expect(app(VoucherService::class)->discountAmount($voucher, 20000))->toBe(20000);
});

it('returns zero discount for empty subtotal', function (): void {
    $voucher = Voucher::factory()->fixed(30000)->create();

    expect(app(VoucherService::class)->discountAmount($voucher, 0))->toBe(0);
});

it('lists only active in-date vouchers meeting restrictions', function (): void {
    $user = User::factory()->create();

    $available = Voucher::factory()->percentage(10)->create();
    $inactive = Voucher::factory()->inactive()->create();
    $expired = Voucher::factory()->expired()->create();
    $upcoming = Voucher::factory()->upcoming()->create();
    $minOrderNotMet = Voucher::factory()->fixed(20000)->create(['min_order_amount' => 500000]);

    $list = app(VoucherService::class)->availableFor($user, 250000);

    expect($list->pluck('id')->all())->toBe([$available->id])
        ->and($list->pluck('id'))->not->toContain($inactive->id)
        ->and($list->pluck('id'))->not->toContain($expired->id)
        ->and($list->pluck('id'))->not->toContain($upcoming->id)
        ->and($list->pluck('id'))->not->toContain($minOrderNotMet->id);
});

it('hides vouchers whose global quota is exhausted', function (): void {
    $user = User::factory()->create();
    $voucher = Voucher::factory()->percentage(10)->create(['max_uses' => 1]);
    $otherUser = User::factory()->create();

    VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'order_id' => Order::factory()->create(['user_id' => $otherUser->id])->id,
        'user_id' => $otherUser->id,
    ]);

    expect(app(VoucherService::class)->availableFor($user, 100000))->toBeEmpty();
});

it('hides vouchers whose per-user quota is exhausted', function (): void {
    $user = User::factory()->create();
    $voucher = Voucher::factory()->percentage(10)->create([
        'max_uses' => null,
        'max_uses_per_user' => 1,
    ]);

    VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'order_id' => Order::factory()->create(['user_id' => $user->id])->id,
        'user_id' => $user->id,
    ]);

    expect(app(VoucherService::class)->availableFor($user, 100000))->toBeEmpty();
});

it('rejects vouchers that are not active today', function (): void {
    $user = User::factory()->create();
    $voucher = Voucher::factory()->expired()->create();

    expect(fn () => app(VoucherService::class)->assertUsable($voucher, 100000, $user))
        ->toThrow(RuntimeException::class, 'Voucher tidak berlaku.');
});

it('rejects vouchers below minimum order amount', function (): void {
    $user = User::factory()->create();
    $voucher = Voucher::factory()->percentage(10)->create(['min_order_amount' => 200000]);

    expect(fn () => app(VoucherService::class)->assertUsable($voucher, 100000, $user))
        ->toThrow(RuntimeException::class, 'minimal belanja');
});

it('rejects vouchers whose global quota is used up', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $voucher = Voucher::factory()->percentage(10)->create(['max_uses' => 1]);

    VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'order_id' => Order::factory()->create(['user_id' => $otherUser->id])->id,
        'user_id' => $otherUser->id,
    ]);

    expect(fn () => app(VoucherService::class)->assertUsable($voucher, 100000, $user))
        ->toThrow(RuntimeException::class, 'Kuota voucher sudah habis.');
});

it('rejects vouchers beyond per-user quota', function (): void {
    $user = User::factory()->create();
    $voucher = Voucher::factory()->percentage(10)->create(['max_uses_per_user' => 1]);

    VoucherUsage::create([
        'voucher_id' => $voucher->id,
        'order_id' => Order::factory()->create(['user_id' => $user->id])->id,
        'user_id' => $user->id,
    ]);

    expect(fn () => app(VoucherService::class)->assertUsable($voucher, 100000, $user))
        ->toThrow(RuntimeException::class, 'maksimal');
});

it('accepts an unused voucher within its restrictions', function (): void {
    $user = User::factory()->create();
    $voucher = Voucher::factory()->percentage(10)->create([
        'min_order_amount' => 50000,
        'max_uses' => 5,
        'max_uses_per_user' => 2,
    ]);

    expect(fn () => app(VoucherService::class)->assertUsable($voucher, 100000, $user))
        ->not->toThrow(RuntimeException::class);
});
