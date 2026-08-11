<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('lists only admin users', function (): void {
    User::factory()->admin()->create(['name' => 'Staf Toko']);
    User::factory()->create(['name' => 'Budi Pelanggan']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertSuccessful()
        ->assertSee('Staf Toko')
        ->assertDontSee('Budi Pelanggan');
});

it('searches users by name or email', function (): void {
    User::factory()->admin()->create(['name' => 'Andi Admin', 'email' => 'andi@example.com']);
    User::factory()->admin()->create(['name' => 'Siti Staf', 'email' => 'siti@example.com']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['search' => 'andi']))
        ->assertSuccessful()
        ->assertSee('Andi Admin')
        ->assertDontSee('Siti Staf');

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['search' => 'siti@example.com']))
        ->assertSee('Siti Staf');
});

it('creates an admin user who can access the panel', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'name' => 'Staf Baru',
            'email' => 'staf@example.com',
            'password' => 'rahasia123',
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.users.index'));

    $user = User::where('email', 'staf@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->is_admin)->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertSuccessful();
});

it('requires a unique email', function (): void {
    User::factory()->admin()->create(['email' => 'sama@example.com']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'name' => 'Staf Duplikat',
            'email' => 'sama@example.com',
            'password' => 'rahasia123',
        ])
        ->assertSessionHasErrors('email');
});

it('updates user without touching the password', function (): void {
    $user = User::factory()->admin()->create(['name' => 'Lama']);

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $user), [
            'name' => 'Baru',
            'email' => $user->email,
            'password' => '',
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.users.index'));

    $user->refresh();

    expect($user->name)->toBe('Baru')
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

it('updates the password when provided', function (): void {
    $user = User::factory()->admin()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'password-baru-123',
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.users.index'));

    expect(Hash::check('password-baru-123', $user->refresh()->password))->toBeTrue();
});

it('cannot deactivate own account', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'is_active' => '0',
        ])
        ->assertSessionHasErrors('is_active');

    expect($this->admin->refresh()->is_active)->toBeTrue();
});

it('toggles a user active state', function (): void {
    $other = User::factory()->admin()->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.users.toggle-active', $other))
        ->assertRedirect();

    expect($other->refresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)
        ->patch(route('admin.users.toggle-active', $other))
        ->assertRedirect();

    expect($other->refresh()->is_active)->toBeTrue();
});

it('cannot toggle off own account', function (): void {
    $this->actingAs($this->admin)
        ->patch(route('admin.users.toggle-active', $this->admin))
        ->assertRedirect();

    expect($this->admin->refresh()->is_active)->toBeTrue();
});

it('can re-activate an inactive user even when only one active admin remains', function (): void {
    $other = User::factory()->admin()->create(['is_active' => false]);

    $this->actingAs($this->admin)
        ->patch(route('admin.users.toggle-active', $other))
        ->assertRedirect();

    expect($other->refresh()->is_active)->toBeTrue();
});

it('cannot deactivate the last active admin', function (): void {
    $other = User::factory()->admin()->create(['is_active' => false]);

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $other), [
            'name' => $other->name,
            'email' => $other->email,
            'is_active' => '0',
        ])
        ->assertSessionHasErrors('is_active');
});

it('can deactivate another admin when more than one active admin exists', function (): void {
    $other = User::factory()->admin()->create();
    $third = User::factory()->admin()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $other), [
            'name' => $other->name,
            'email' => $other->email,
            'is_active' => '0',
        ])
        ->assertRedirect(route('admin.users.index'));

    expect($other->refresh()->is_active)->toBeFalse()
        ->and($third->refresh()->is_active)->toBeTrue();
});

it('cannot edit a customer from the user page', function (): void {
    $customer = User::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.users.edit', $customer))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->put(route('admin.users.update', $customer), [
            'name' => 'Hacker',
            'email' => $customer->email,
        ])
        ->assertForbidden();
});

it('never allows changing is_admin from the user form', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'name' => 'Staf',
            'email' => 'staf@example.com',
            'password' => 'rahasia123',
            'is_admin' => false,
        ])
        ->assertRedirect(route('admin.users.index'));

    expect(User::where('email', 'staf@example.com')->first()->is_admin)->toBeTrue();
});

it('rejects non-admin users with 403', function (): void {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});
