<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

final class LogoutResponse implements LogoutResponseContract
{
    /**
     * Create an HTTP response after a successful logout.
     *
     * Inertia::location() (409 + X-Inertia-Location) agar client Inertia
     * langsung full page reload ke halaman login — 302 biasa membuat UI
     * diam dan user harus refresh manual.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return Inertia::location(route('login'));
    }
}
