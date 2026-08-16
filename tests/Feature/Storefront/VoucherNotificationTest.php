<?php

use App\Models\User;
use App\Models\Voucher;
use App\Notifications\VoucherCreatedNotification;
use Illuminate\Notifications\DatabaseNotification;

it('notifies all active customers when an active voucher is created', function (): void {
    $customerA = User::factory()->customer()->create();
    $customerB = User::factory()->customer()->create();

    Voucher::factory()->percentage(10)->create([
        'nama' => 'Voucher Ramadhan',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
    ]);

    expect($customerA->notifications()->count())->toBe(1)
        ->and($customerB->notifications()->count())->toBe(1)
        ->and($customerA->notifications()->first()->type)->toBe(VoucherCreatedNotification::class);

    $notification = $customerA->notifications()->first();

    expect($notification->data['message'])->toContain('Voucher Ramadhan')
        ->and($notification->data['message'])->toContain('10%');
});

it('does not notify admins or inactive users', function (): void {
    $admin = User::factory()->admin()->create();
    $inactive = User::factory()->customer()->create(['is_active' => false]);

    Voucher::factory()->percentage(10)->create();

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('does not notify for inactive vouchers', function (): void {
    $customer = User::factory()->customer()->create();

    Voucher::factory()->inactive()->create();

    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('does not notify for upcoming vouchers that have not started', function (): void {
    $customer = User::factory()->customer()->create();

    Voucher::factory()->upcoming()->create();

    expect(DatabaseNotification::query()->count())->toBe(0);
});
