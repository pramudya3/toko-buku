<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman profil storefront utama — /profil.
 *
 * Backend (update akun, alamat, hapus akun) diwarisi dari ProfilePcdController;
 * hanya komponen Inertia yang dirender berbeda (desain editorial).
 */
class StorefrontProfileController extends ProfilePcdController
{
    public function edit(Request $request): Response
    {
        return Inertia::render('storefront/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Redirect balik ke /profil (bukan /pcd/profil).
     */
    protected function profileEditRoute(): string
    {
        return 'storefront.profile.edit';
    }
}
