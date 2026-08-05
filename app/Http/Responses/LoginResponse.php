<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

final class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response after a successful login.
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
        $defaultPath = $isAdmin
            ? route('admin.dashboard', absolute: false)
            : url('/');

        if (! $isAdmin) {
            $intended = $request->session()->get('url.intended');
            $intendedPath = is_string($intended) ? parse_url($intended, PHP_URL_PATH) : null;

            if (is_string($intendedPath) && str_starts_with($intendedPath, '/admin')) {
                $request->session()->forget('url.intended');
            }
        }

        return redirect()->intended($defaultPath);
    }
}
