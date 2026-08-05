<?php

use App\Http\Controllers\Settings\AddressController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/akun');

    // Akun: nama & email
    Route::get('settings/akun', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/akun', [ProfileController::class, 'update'])->name('profile.update');

    // Alamat lengkap
    Route::get('settings/alamat', [AddressController::class, 'edit'])->name('address.edit');
    Route::patch('settings/alamat', [AddressController::class, 'update'])->name('address.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/akun', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});
