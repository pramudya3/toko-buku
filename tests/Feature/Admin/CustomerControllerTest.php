<?php

use App\Enums\CustomerTier;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('lists customers with search', function (): void {
    User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@example.com']);
    User::factory()->create(['name' => 'Siti Aminah', 'email' => 'siti@example.com']);

    $this->actingAs($this->admin)
        ->get(route('admin.customers.index', ['search' => 'budi']))
        ->assertSuccessful()
        ->assertSee('Budi Santoso')
        ->assertDontSee('Siti Aminah');

    $this->actingAs($this->admin)
        ->get(route('admin.customers.index', ['search' => 'siti@example.com']))
        ->assertSee('Siti Aminah');
});

it('does not list admins as customers', function (): void {
    User::factory()->admin()->create(['name' => 'Admin Tersembunyi']);

    $this->actingAs($this->admin)
        ->get(route('admin.customers.index'))
        ->assertSuccessful()
        ->assertDontSee('Admin Tersembunyi');
});

it('updates customer profile fields', function (): void {
    $customer = User::factory()->create(['name' => 'Lama']);

    $this->actingAs($this->admin)
        ->put(route('admin.customers.update', $customer), [
            'name' => 'Baru',
            'email' => $customer->email,
            'status_pelanggan' => CustomerTier::Reseller->value,
            'whatsapp_number' => '081234567890',
            'provinsi' => 'Jawa Timur',
        ])
        ->assertRedirect(route('admin.customers.index'));

    $customer->refresh();

    expect($customer->name)->toBe('Baru')
        ->and($customer->status_pelanggan)->toBe(CustomerTier::Reseller);
});

it('never allows changing is_admin from the customer form (CUST-04)', function (): void {
    $customer = User::factory()->create(['is_admin' => false]);

    $this->actingAs($this->admin)
        ->put(route('admin.customers.update', $customer), [
            'name' => 'Hacker',
            'email' => $customer->email,
            'status_pelanggan' => CustomerTier::Reguler->value,
            'is_admin' => true, // dicoba lewat request
        ])
        ->assertRedirect(route('admin.customers.index'));

    expect($customer->fresh()->is_admin)->toBeFalse();
});

it('blocks editing admin accounts from the customer page', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.customers.edit', $admin))
        ->assertForbidden();
});

it('rejects invalid WhatsApp numbers when creating a customer', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.customers.store'), [
            'name' => 'Pelanggan WA',
            'email' => 'wa@example.com',
            'password' => 'rahasia123',
            'status_pelanggan' => CustomerTier::Reguler->value,
            'whatsapp_number' => '0812-3456-7890',
        ])
        ->assertSessionHasErrors('whatsapp_number');

    expect(User::where('email', 'wa@example.com')->exists())->toBeFalse();
});

it('rejects invalid WhatsApp numbers when updating a customer', function (): void {
    $customer = User::factory()->create(['whatsapp_number' => '081234567890']);

    $this->actingAs($this->admin)
        ->put(route('admin.customers.update', $customer), [
            'name' => $customer->name,
            'email' => $customer->email,
            'status_pelanggan' => CustomerTier::Reguler->value,
            'whatsapp_number' => '+6281234567890',
        ])
        ->assertSessionHasErrors('whatsapp_number');

    // Nomor lama tidak berubah saat update ditolak.
    expect($customer->fresh()->whatsapp_number)->toBe('081234567890');
});

it('creates a new customer with active status', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.customers.store'), [
            'name' => 'Pelanggan Baru',
            'email' => 'baru@example.com',
            'password' => 'rahasia123',
            'status_pelanggan' => CustomerTier::Bazaf->value,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.customers.index'));

    $customer = User::where('email', 'baru@example.com')->firstOrFail();

    expect($customer->is_admin)->toBeFalse()
        ->and($customer->status_pelanggan)->toBe(CustomerTier::Bazaf)
        ->and($customer->is_active)->toBeTrue();
});

it('can set customer status to inactive', function (): void {
    $customer = User::factory()->create(['is_admin' => false, 'is_active' => true]);

    $this->actingAs($this->admin)
        ->put(route('admin.customers.update', $customer), [
            'name' => $customer->name,
            'email' => $customer->email,
            'status_pelanggan' => CustomerTier::Reguler->value,
            'is_active' => '0',
        ])
        ->assertRedirect(route('admin.customers.index'));

    expect($customer->fresh()->is_active)->toBeFalse();
});
