<?php

use App\Enums\PaymentMethod as PaymentMethodEnum;
use App\Models\PaymentMethod;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('stores a custom payment method', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.settings.pembayaran.store'), [
            'code' => 'qris',
            'name' => 'QRIS',
        ])
        ->assertRedirect();

    expect(PaymentMethod::first())
        ->code->toBe('qris')
        ->name->toBe('QRIS')
        ->is_active->toBeTrue();
});

it('requires code and name for a payment method', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.settings.pembayaran.store'), [])
        ->assertSessionHasErrors(['code', 'name']);

    expect(PaymentMethod::count())->toBe(0);
});

it('rejects duplicate payment method codes', function (): void {
    PaymentMethod::factory()->create(['code' => 'transfer']);

    $this->actingAs($this->admin)
        ->post(route('admin.settings.pembayaran.store'), [
            'code' => 'transfer',
            'name' => 'Transfer Lagi',
        ])
        ->assertSessionHasErrors('code');
});

it('updates a payment method name and active state', function (): void {
    $method = PaymentMethod::factory()->create(['name' => 'COD', 'is_active' => true]);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.pembayaran.update', $method), [
            'name' => 'COD / Ambil Sendiri',
            'is_active' => '0',
        ])
        ->assertRedirect();

    expect($method->fresh())
        ->name->toBe('COD / Ambil Sendiri')
        ->is_active->toBeFalse();
});

it('soft deletes and restores a payment method', function (): void {
    $method = PaymentMethod::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.settings.pembayaran.destroy', $method))
        ->assertRedirect();

    expect($method->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('admin.settings.pembayaran.restore', $method))
        ->assertRedirect();

    expect($method->fresh()->trashed())->toBeFalse();
});

it('resolves labels for built-in and custom payment methods', function (): void {
    expect(PaymentMethodEnum::labelFor('transfer'))->toBe('Transfer');

    PaymentMethod::factory()->create(['code' => 'qris', 'name' => 'QRIS']);

    expect(PaymentMethodEnum::labelFor('qris'))->toBe('QRIS')
        ->and(PaymentMethodEnum::labelFor('metode-hantu'))->toBe('metode-hantu');
});

it('blocks customers from payment method settings', function (): void {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->post(route('admin.settings.pembayaran.store'), [
            'code' => 'qris',
            'name' => 'QRIS',
        ])
        ->assertForbidden();
});
