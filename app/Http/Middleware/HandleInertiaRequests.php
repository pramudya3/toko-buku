<?php

namespace App\Http\Middleware;

use App\Enums\OrderStatus;
use App\Models\Book;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            // Identitas toko dari pengaturan Lembaga — dipakai di judul sidebar.
            'storeName' => Setting::get('store_nama_lembaga') ?: config('app.name'),
            'storeLogoUrl' => Setting::get('store_logo_url', ''),
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
            'cartCount' => function (): int {
                $cart = (array) session('cart', []);
                $bookIds = [];

                foreach ($cart as $key => $entry) {
                    $qty = is_array($entry) ? (int) ($entry['qty'] ?? 0) : (int) $entry;

                    if ($qty > 0) {
                        $bookIds[] = explode(':', (string) $key)[0];
                    }
                }

                // Hanya uuid valid — abaikan kunci basi era id integer (jangan
                // di-cast ke int: uuid v7 berawalan angka, (int) merusaknya).
                $bookIds = array_values(array_unique(array_filter(
                    $bookIds,
                    static fn (mixed $id): bool => is_string($id) && Str::isUuid($id),
                )));

                if ($bookIds === []) {
                    return 0;
                }

                return (int) Book::query()
                    ->whereIn('id', $bookIds)
                    ->where('aktif', true)
                    ->count();
            },
            // Jumlah order menunggu konfirmasi (badge sidebar admin).
            'pendingOrdersCount' => fn (): int => $request->user()?->is_admin
                ? (int) Order::query()->where('status', OrderStatus::MenungguKonfirmasi)->count()
                : 0,
            // Jumlah order aktif customer (badge menu "Pesanan Saya") —
            // status belum selesai: menunggu konfirmasi / diproses / dikirim.
            'activeOrdersCount' => fn (): int => $request->user()
                ? (int) Order::query()
                    ->where('user_id', $request->user()->id)
                    ->whereIn('status', [
                        OrderStatus::MenungguKonfirmasi->value,
                        OrderStatus::Diproses->value,
                        OrderStatus::Dikirim->value,
                    ])
                    ->count()
                : 0,
            // Notifikasi customer (lonceng storefront) — 5 terbaru + jumlah belum dibaca.
            'notifications' => fn (): array => $request->user()?->is_admin
                ? []
                : $request->user()
                    ?->notifications()
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'data', 'read_at', 'created_at'])
                    ->map(fn ($notification): array => [
                        'id' => $notification->getKey(),
                        'message' => $notification->data['message'] ?? '',
                        'read_at' => $notification->read_at,
                        'created_at' => $notification->created_at,
                    ])
                    ->all() ?? [],
            'notificationsCount' => fn (): int => $request->user()?->is_admin
                ? 0
                : (int) $request->user()?->unreadNotifications()->count() ?? 0,
        ];
    }
}
