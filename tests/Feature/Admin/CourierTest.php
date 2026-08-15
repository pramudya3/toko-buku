<?php

use App\Models\Courier;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('stores a custom courier', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.settings.ekspedisi.store'), [
            'code' => 'jnt',
            'name' => 'J&T Express',
        ])
        ->assertRedirect();

    expect(Courier::first())
        ->code->toBe('jnt')
        ->name->toBe('J&T Express')
        ->is_active->toBeTrue();
});

it('requires code and name for a courier', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.settings.ekspedisi.store'), [])
        ->assertSessionHasErrors(['code', 'name']);

    expect(Courier::count())->toBe(0);
});

it('rejects duplicate courier codes', function (): void {
    Courier::factory()->create(['code' => 'jne']);

    $this->actingAs($this->admin)
        ->post(route('admin.settings.ekspedisi.store'), [
            'code' => 'jne',
            'name' => 'JNE Lagi',
        ])
        ->assertSessionHasErrors('code');
});

it('updates a courier name and active state', function (): void {
    $courier = Courier::factory()->create(['name' => 'JNE', 'is_active' => true]);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.ekspedisi.update', $courier), [
            'name' => 'JNE Reguler',
            'is_active' => '0',
        ])
        ->assertRedirect();

    expect($courier->fresh())
        ->name->toBe('JNE Reguler')
        ->is_active->toBeFalse();
});

it('soft deletes and restores a courier', function (): void {
    $courier = Courier::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.settings.ekspedisi.destroy', $courier))
        ->assertRedirect();

    expect($courier->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('admin.settings.ekspedisi.restore', $courier))
        ->assertRedirect();

    expect($courier->fresh()->trashed())->toBeFalse();
});

it('bulk activates and deactivates couriers', function (): void {
    $jne = Courier::factory()->create(['is_active' => false]);
    $jnt = Courier::factory()->create(['is_active' => false]);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.ekspedisi.bulk'), [
            'ids' => [$jne->id, $jnt->id],
            'is_active' => '1',
        ])
        ->assertRedirect();

    expect($jne->fresh()->is_active)->toBeTrue()
        ->and($jnt->fresh()->is_active)->toBeTrue();

    $this->actingAs($this->admin)
        ->put(route('admin.settings.ekspedisi.bulk'), [
            'ids' => [$jne->id, $jnt->id],
            'is_active' => '0',
        ])
        ->assertRedirect();

    expect($jne->fresh()->is_active)->toBeFalse()
        ->and($jnt->fresh()->is_active)->toBeFalse();
});

it('validates the bulk courier payload', function (): void {
    $courier = Courier::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.settings.ekspedisi.bulk'), [
            'ids' => ['tidak-ada'],
            'is_active' => '1',
        ])
        ->assertSessionHasErrors(['ids.0']);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.ekspedisi.bulk'), [
            'ids' => [$courier->id],
            'is_active' => 'bukan-bool',
        ])
        ->assertSessionHasErrors('is_active');

    expect($courier->fresh()->is_active)->toBeTrue();
});

it('blocks customers from courier settings', function (): void {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->post(route('admin.settings.ekspedisi.store'), [
            'code' => 'jne',
            'name' => 'JNE',
        ])
        ->assertForbidden();
});
