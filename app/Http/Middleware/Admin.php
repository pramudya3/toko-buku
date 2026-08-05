<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guard akses panel admin (AUTH-02, AUTH-05).
 *
 * Hanya user dengan is_admin = true yang boleh melewati middleware ini.
 * Backend memvalidasi, bukan hanya menyembunyikan menu di UI.
 */
class Admin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_admin) {
            abort(403, 'Akses panel admin ditolak.');
        }

        return $next($request);
    }
}
