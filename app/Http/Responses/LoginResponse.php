<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

final class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response after a successful login.
     *
     * Redirect 302 biasa tidak bisa diproses Inertia client (response HTML
     * tanpa header X-Inertia) → UI diam dan user harus refresh manual.
     * Inertia::location() mengembalikan 409 + header X-Inertia-Location
     * sehingga client langsung full page reload ke tujuan.
     *
     * Admin SELALU diarahkan ke dashboard admin, apa pun url.intended
     * sebelumnya (intended hanya berlaku untuk customer non-admin).
     *
     * @param Request $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        $user = $request->user();
        $isAdmin = $user instanceof User && $user->is_admin;

        if ($isAdmin) {
            return Inertia::location(route('admin.dashboard'));
        }

        // Customer: kembali ke halaman yang dituju sebelumnya (mis. checkout),
        // kecuali intended mengarah ke area admin — customer tidak boleh masuk.
        $intended = $request->session()->pull('url.intended');
        $intendedPath = is_string($intended) ? parse_url($intended, PHP_URL_PATH) : null;

        if (is_string($intendedPath) && str_starts_with($intendedPath, '/admin')) {
            $intended = null;
        }

        return Inertia::location($intended ?: url('/'));
    }
}
