<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Profil storefront paralel /pcd/profil — memakai request & aturan validasi
 * yang sama dengan halaman settings akun/alamat.
 */
beforeEach(function (): void {
    $this->customer = User::factory()->create();
});

it('redirects guests to login on pcd profile', function (): void {
    $this->get(route('pcd.profile.edit'))
        ->assertRedirect(route('login'));
});

it('renders the pcd profile page', function (): void {
    $this->actingAs($this->customer)
        ->get(route('pcd.profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront-pcd/Profile')
            ->where('mustVerifyEmail', false));
});

it('updates the account name and email via the pcd profile route', function (): void {
    $this->actingAs($this->customer)
        ->patch(route('pcd.profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('pcd.profile.edit'));

    $this->customer->refresh();

    expect($this->customer->name)->toBe('Test User')
        ->and($this->customer->email)->toBe('test@example.com')
        ->and($this->customer->email_verified_at)->toBeNull();
});

it('keeps email verification when the email address is unchanged', function (): void {
    $this->actingAs($this->customer)
        ->patch(route('pcd.profile.update'), [
            'name' => 'Test User',
            'email' => $this->customer->email,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('pcd.profile.edit'));

    expect($this->customer->refresh()->email_verified_at)->not->toBeNull();
});

it('updates the address via the pcd profile route', function (): void {
    $this->actingAs($this->customer)
        ->patch(route('pcd.profile.update-address'), [
            'alamat' => 'Jl. Merdeka No. 1',
            'provinsi' => 'Jawa Timur',
            'kabupaten_kota' => 'Malang',
            'kecamatan' => 'Lowokwaru',
            'kelurahan' => 'Tunggulwulung',
            'village_code' => '3507372006',
            'kode_pos' => '65144',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('pcd.profile.edit'));

    expect($this->customer->refresh()->alamat)->toBe('Jl. Merdeka No. 1');
});

it('deletes the account via the pcd profile route', function (): void {
    $this->actingAs($this->customer)
        ->delete(route('pcd.profile.destroy'), [
            'password' => 'password',
        ])
        ->assertRedirect('/');

    expect(User::find($this->customer->id))->toBeNull();
});
