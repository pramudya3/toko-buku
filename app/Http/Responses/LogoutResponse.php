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
     * Redirect 302 biasa membuat Inertia client diam (response HTML tanpa
     * header X-Inertia tidak bisa diproses) → user harus refresh manual.
     * Inertia::location() mengembalikan 409 + header X-Inertia-Location
     * sehingga client melakukan full page reload ke halaman tujuan.
     *
     * @param Request $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return Inertia::location(url('/'));
    }
}
