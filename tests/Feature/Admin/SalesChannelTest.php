<?php

use App\Enums\SalesChannel as SalesChannelEnum;
use App\Models\SalesChannel;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('stores a custom sales channel', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.settings.sumber-penjualan.store'), [
            'code' => 'shopee',
            'name' => 'Shopee',
        ])
        ->assertRedirect();

    expect(SalesChannel::first())
        ->code->toBe('shopee')
        ->name->toBe('Shopee')
        ->is_active->toBeTrue();
});

it('requires code and name for a sales channel', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.settings.sumber-penjualan.store'), [])
        ->assertSessionHasErrors(['code', 'name']);

    expect(SalesChannel::count())->toBe(0);
});

it('rejects duplicate sales channel codes', function (): void {
    SalesChannel::factory()->create(['code' => 'toko']);

    $this->actingAs($this->admin)
        ->post(route('admin.settings.sumber-penjualan.store'), [
            'code' => 'toko',
            'name' => 'Toko Lagi',
        ])
        ->assertSessionHasErrors('code');
});

it('updates a sales channel name and active state', function (): void {
    $channel = SalesChannel::factory()->create(['name' => 'Toko', 'is_active' => true]);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.sumber-penjualan.update', $channel), [
            'name' => 'Toko Offline',
            'is_active' => '0',
        ])
        ->assertRedirect();

    expect($channel->fresh())
        ->name->toBe('Toko Offline')
        ->is_active->toBeFalse();
});

it('soft deletes and restores a sales channel', function (): void {
    $channel = SalesChannel::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.settings.sumber-penjualan.destroy', $channel))
        ->assertRedirect();

    expect($channel->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('admin.settings.sumber-penjualan.restore', $channel))
        ->assertRedirect();

    expect($channel->fresh()->trashed())->toBeFalse();
});

it('resolves labels for built-in and custom sales channels', function (): void {
    expect(SalesChannelEnum::labelFor('shopee'))->toBe('Shopee');

    SalesChannel::factory()->create(['code' => 'blibli', 'name' => 'Blibli']);

    expect(SalesChannelEnum::labelFor('blibli'))->toBe('Blibli')
        ->and(SalesChannelEnum::labelFor('channel-hantu'))->toBe('channel-hantu');
});

it('blocks customers from sales channel settings', function (): void {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->post(route('admin.settings.sumber-penjualan.store'), [
            'code' => 'shopee',
            'name' => 'Shopee',
        ])
        ->assertForbidden();
});
