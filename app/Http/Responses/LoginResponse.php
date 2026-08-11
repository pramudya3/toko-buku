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
     * Inertia::location() mengembalikan 409 + header X-Inertia-Location
     * sehingga client Inertia melakukan full page reload ke tujuan (302
     * biasa membuat UI diam dan user harus refresh manual).
     *
     * Admin SELALU diarahkan ke dashboard admin, apa pun url.intended
     * sebelumnya. Customer kembali ke halaman intended, dengan sanitasi:
     * host (termasuk host internal di belakang proxy) dibuang, hanya path
     * relatif yang dipakai — anti open-redirect.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false]);
        }

        $user = $request->user();
        $isAdmin = $user instanceof User && $user->is_admin;

        if ($isAdmin) {
            $request->session()->forget('url.intended');

            return Inertia::location(route('admin.dashboard'));
        }

        // Customer: kembali ke halaman yang dituju sebelumnya (mis. checkout),
        // kecuali intended mengarah ke area admin — customer tidak boleh masuk.
        $intended = $request->session()->pull('url.intended');
        $path = null;

        if (is_string($intended)) {
            $intendedPath = parse_url($intended, PHP_URL_PATH);
            $intendedQuery = parse_url($intended, PHP_URL_QUERY);
            $candidate = $intendedPath.($intendedQuery !== null ? "?{$intendedQuery}" : '');

            if (is_string($candidate)
                && str_starts_with($candidate, '/')
                && ! str_starts_with($candidate, '//')
                && ! str_starts_with($candidate, '/admin')) {
                $path = $candidate;
            }
        }

        return Inertia::location($path ?: url('/'));
    }
}
