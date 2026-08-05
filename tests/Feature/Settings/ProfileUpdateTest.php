<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh()->trashed())->toBeTrue();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
test('deleting account is a soft delete', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete(route('profile.destroy'), [
        'password' => 'password',
    ]);

    expect($user->fresh()->trashed())->toBeTrue();
});

test('email of deleted account can be registered again', function () {
    $user = User::factory()->create(['email' => 'bekas@example.com']);

    $this->actingAs($user)->delete(route('profile.destroy'), [
        'password' => 'password',
    ]);

    $response = $this->post('/register', [
        'name' => 'User Baru',
        'email' => 'bekas@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect();
    expect(User::where('email', 'bekas@example.com')->count())->toBe(1);
});

test('profile can save full address', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'alamat' => 'Jl. Merdeka 1',
            'provinsi' => 'JAWA TIMUR',
            'kabupaten_kota' => 'KOTA MALANG',
            'kecamatan' => 'KLOJEN',
            'kode_pos' => '65144',
        ])
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh()->provinsi)->toBe('JAWA TIMUR')
        ->and($user->fresh()->kode_pos)->toBe('65144');
});
