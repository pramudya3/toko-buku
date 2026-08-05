<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // Kredensial demo utk tombol "isi otomatis" di halaman login (AUTH-04).
            'demoCredentials' => app()->environment('local') ? [
                'email' => 'admin@tokobuku.test',
                'password' => 'password',
            ] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Jumlah item keranjang storefront (session) — hanya buku yang masih aktif.
            'cartCount' => function () use ($request): int {
                $cart = array_filter((array) session('cart', []), fn ($qty) => $qty > 0);

                if ($cart === []) {
                    return 0;
                }

                return (int) \App\Models\Book::query()
                    ->whereIn('id', array_keys($cart))
                    ->where('aktif', true)
                    ->count();
            },
            // Jumlah order menunggu konfirmasi (badge sidebar admin).
            'pendingOrdersCount' => fn (): int => $request->user()?->is_admin
                ? (int) \App\Models\Order::query()->where('status', \App\Enums\OrderStatus::MenungguKonfirmasi)->count()
                : 0,
        ];
    }
}
