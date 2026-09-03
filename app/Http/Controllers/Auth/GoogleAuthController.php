<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class GoogleAuthController extends Controller
{
    /**
     * Redirect ke Google OAuth.
     */
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Callback dari Google setelah user menyetujui.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors([
                'email' => 'Gagal login dengan Google. Silakan coba lagi.',
            ]);
        }

        $email = $googleUser->getEmail();
        $googleId = $googleUser->getId();
        $name = $googleUser->getName() ?: $googleUser->getNickname() ?: Str::before($email, '@');
        $avatar = $googleUser->getAvatar();

        if (! $email) {
            return redirect()->route('login')->withErrors([
                'email' => 'Akun Google tidak memiliki email.',
            ]);
        }

        // Cari user by google_id atau email
        $user = User::where('google_id', $googleId)->first();

        if (! $user) {
            $user = User::where('email', $email)->first();

            if ($user) {
                // Link akun yang sudah ada (daftar manual) ke Google
                if (! $user->is_active) {
                    return redirect()->route('login')->withErrors([
                        'email' => 'Akun Anda dinonaktifkan. Hubungi admin.',
                    ]);
                }

                $user->update([
                    'google_id' => $googleId,
                    'avatar' => $avatar,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            } else {
                // Buat user baru (customer)
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'google_id' => $googleId,
                    'avatar' => $avatar,
                    'email_verified_at' => now(),
                    'password' => null,
                    'is_admin' => false,
                    'is_active' => true,
                ]);
            }
        } else {
            // User sudah punya google_id — cek aktif & update avatar jika perlu
            if (! $user->is_active) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Akun Anda dinonaktifkan. Hubungi admin.',
                ]);
            }

            if ($avatar && $user->avatar !== $avatar) {
                $user->update(['avatar' => $avatar]);
            }
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        // Admin tetap bisa login via Google, arahkan ke dashboard admin
        if ($user->is_admin) {
            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        return redirect()->intended(route('home', absolute: false));
    }
}
