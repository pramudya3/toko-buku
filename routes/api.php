<?php

use App\Http\Controllers\Api\Pos\PosAuthController;
use App\Http\Controllers\Api\Pos\PosCustomerController;
use App\Http\Controllers\Api\Pos\PosOrderController;
use App\Http\Controllers\Api\Pos\PosReportController;
use App\Http\Controllers\Api\Pos\PosSyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| POS API — kasir-toko-buku (offline-first, Reverb realtime)
|--------------------------------------------------------------------------
|
| Auth: Sanctum personal access token (ability pos:*)
| Prefix: /api/pos
|
*/

// Public — login
Route::post('pos/login', [PosAuthController::class, 'login'])
    ->middleware('throttle:10,1')
    ->name('api.pos.login');

// Protected — semua butuh token pos
Route::middleware(['auth:sanctum', 'pos'])->prefix('pos')->name('api.pos.')->group(function () {
    Route::get('health', [PosSyncController::class, 'health'])->name('health');
    Route::post('logout', [PosAuthController::class, 'logout'])->name('logout');

    // Pull master incremental
    Route::get('sync/pull', [PosSyncController::class, 'pull'])->name('sync.pull');

    // Books & customers lookup (online fallback)
    Route::get('books', [PosSyncController::class, 'books'])->name('books');
    Route::get('books/{book}/editions', [PosSyncController::class, 'editions'])->name('books.editions');
    Route::get('customers', [PosCustomerController::class, 'index'])->name('customers.index');
    Route::post('customers', [PosCustomerController::class, 'store'])->name('customers.store');

    // Orders — idempotent client_uuid
    Route::post('orders', [PosOrderController::class, 'store'])->name('orders.store');
    Route::get('orders', [PosOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order:client_uuid}', [PosOrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order:client_uuid}/void', [PosOrderController::class, 'void'])->name('orders.void');

    // Reports
    Route::get('reports/daily', [PosReportController::class, 'daily'])->name('reports.daily');
});

// Sanctum user (debug)
Route::middleware('auth:sanctum')->get('user', function (Request $request) {
    return $request->user();
});
