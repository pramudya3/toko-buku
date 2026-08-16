<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Notifikasi customer (lonceng di header storefront).
 */
class NotificationController extends Controller
{
    /**
     * Tandai satu notifikasi sebagai dibaca.
     */
    public function markRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        $request->user()->notifications()
            ->whereKey($notification->getKey())
            ->firstOrFail()
            ->markAsRead();

        return back();
    }

    /**
     * Tandai semua notifikasi customer sebagai dibaca.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
