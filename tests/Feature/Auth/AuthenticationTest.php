<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Features;

/**
 * Assert respons Inertia::location — 409 + header X-Inertia-Location.
 */
function assertInertiaLocation(TestResponse $response, string $path): void
{
    $response->assertStatus(409);

    $location = $response->headers->get('X-Inertia-Location') ?? '';
    $locationPath = parse_url($location, PHP_URL_PATH) ?: '/';
    $locationQuery = parse_url($location, PHP_URL_QUERY);
    $actual = $locationPath.($locationQuery !== null ? "?{$locationQuery}" : '');

    expect($actual)->toBe($path);
}

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('customers are sent to the storefront home after login', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ], ['X-Inertia' => 'true']);

    $this->assertAuthenticated();
    assertInertiaLocation($response, '/');
});

test('admins are sent to the admin dashboard', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ], ['X-Inertia' => 'true']);

    assertInertiaLocation($response, '/admin/dashboard');
});

test('admins ignore stale intended urls (proxy/internal host)', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->withSession(['url.intended' => 'http://127.0.0.1:8001/admin/books'])
        ->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ], ['X-Inertia' => 'true']);

    assertInertiaLocation($response, '/admin/dashboard');
    expect(session()->has('url.intended'))->toBeFalse();
});

test('customers return to their intended storefront page', function () {
    $user = User::factory()->create();

    $response = $this->withSession(['url.intended' => 'http://localhost/pesanan-saya?page=2'])
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ], ['X-Inertia' => 'true']);

    // Host diabaikan — path relatif + query dipertahankan.
    assertInertiaLocation($response, '/pesanan-saya?page=2');
});

test('customers ignore internal proxy hosts in intended urls', function () {
    $user = User::factory()->create();

    $response = $this->withSession(['url.intended' => 'http://127.0.0.1:8001/pesanan-saya'])
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ], ['X-Inertia' => 'true']);

    assertInertiaLocation($response, '/pesanan-saya');
});

test('customers are never sent to foreign hosts via intended urls', function () {
    $user = User::factory()->create();

    $response = $this->withSession(['url.intended' => '//evil.com/phishing'])
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ], ['X-Inertia' => 'true']);

    $response->assertStatus(409);
    expect($response->headers->get('X-Inertia-Location'))->not->toContain('evil.com');
});

test('customers are cleared from admin intended pages', function () {
    $user = User::factory()->create();

    $response = $this->withSession(['url.intended' => 'http://localhost/admin/books'])
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ], ['X-Inertia' => 'true']);

    assertInertiaLocation($response, '/');
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('inactive users cannot log in', function () {
    $user = User::factory()->create(['is_active' => false]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ], ['X-Inertia' => 'true']);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('inactive admins cannot log in', function () {
    $admin = User::factory()->admin()->create(['is_active' => false]);

    $response = $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ], ['X-Inertia' => 'true']);

    $response->assertSessionHasErrors('email')
        ->assertSessionHasErrors(['email' => 'Akun Anda dinonaktifkan. Hubungi administrator.']);
    $this->assertGuest();
});

test('users are sent to the login page after logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'), [], ['X-Inertia' => 'true']);

    assertInertiaLocation($response, '/login');

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
