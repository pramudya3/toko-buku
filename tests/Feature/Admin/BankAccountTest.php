<?php

use App\Models\BankAccount;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('lists bank accounts on the settings page', function (): void {
    BankAccount::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.settings.rekening'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/Rekening')
            ->has('accounts', 2));
});

it('stores a bank account', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.settings.rekening.store'), [
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Toko Buku Nusantara',
        ])
        ->assertRedirect();

    expect(BankAccount::first())
        ->bank_name->toBe('BCA')
        ->account_number->toBe('1234567890')
        ->account_holder->toBe('Toko Buku Nusantara')
        ->is_active->toBeTrue();
});

it('requires bank fields', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.settings.rekening.store'), [])
        ->assertSessionHasErrors(['bank_name', 'account_number', 'account_holder']);

    expect(BankAccount::count())->toBe(0);
});

it('updates a bank account', function (): void {
    $account = BankAccount::factory()->create(['bank_name' => 'BCA']);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.rekening.update', $account), [
            'bank_name' => 'BRI',
            'account_number' => '0987654321',
            'account_holder' => 'Pemilik Baru',
            'is_active' => '0',
        ])
        ->assertRedirect();

    expect($account->fresh())
        ->bank_name->toBe('BRI')
        ->account_number->toBe('0987654321')
        ->is_active->toBeFalse();
});

it('soft deletes and restores a bank account', function (): void {
    $account = BankAccount::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.settings.rekening.destroy', $account))
        ->assertRedirect();

    expect($account->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('admin.settings.rekening.restore', $account))
        ->assertRedirect();

    expect($account->fresh()->trashed())->toBeFalse();
});

it('blocks customers from bank account settings', function (): void {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get(route('admin.settings.rekening'))
        ->assertForbidden();
});
