<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\BankAccount;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Halaman order milik customer yang login (storefront).
 */
class MyOrderController extends Controller
{
    protected function page(string $name): string
    {
        return "storefront/{$name}";
    }

    public function index(Request $request): Response
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->withCount(['items', 'items as preorder_items_count' => fn ($q) => $q->where('is_preorder', true)])
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render($this->page('MyOrders'), [
            'orders' => $orders,
            'statusOptions' => OrderStatus::options(),
        ]);
    }

    /**
     * Detail order milik customer — invoice preview + cara bayar + upload bukti.
     */
    public function show(Request $request, Order $order): Response
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        $order->load([
            'items:id,order_id,book_id,is_preorder,judul_snapshot,edition_snapshot,qty,price_original,promo_discount_amount,tier_discount_amount,price_final',
            'dropshipper',
        ]);

        return Inertia::render($this->page('OrderDetail'), [
            'order' => $order,
            'statusOptions' => OrderStatus::options(),
            'bankAccounts' => BankAccount::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'bank_name', 'account_number', 'account_holder']),
        ]);
    }

    /**
     * Invoice customer (layout print A4) — sama dengan nota admin,
     * tanpa data internal (HPP/laba).
     */
    public function invoice(Request $request, Order $order): Response
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        $order->load([
            'items:id,order_id,book_id,book_edition_id,judul_snapshot,edition_snapshot,qty,price_original,promo_discount_amount,tier_discount_amount,price_final',
            'dropshipper',
        ]);

        return Inertia::render('print/orders/Invoice', [
            'order' => $order,
            'store' => [
                'nama' => Setting::get('store_nama_lembaga') ?: config('app.name'),
                'logo_url' => Setting::get('store_logo_url', ''),
                'alamat' => Setting::get('store_alamat', ''),
                'npwp' => Setting::get('store_npwp', ''),
                'telepon' => Setting::get('store_telepon', ''),
                'email' => Setting::get('store_email', ''),
            ],
        ]);
    }

    /**
     * Upload bukti transfer — sinyal pembayaran agar order tidak
     * ter-auto-batal 24 jam & mempercepat verifikasi admin.
     */
    public function uploadBukti(Request $request, Order $order): RedirectResponse
    {
        if ($order->user_id !== $request->user()->id) {
            abort(404);
        }

        if ($order->payment_status->value !== 'menunggu') {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => "Order {$order->no_order} sudah lunas — bukti tidak diperlukan.",
            ]);

            return back();
        }

        $validated = $request->validate([
            'bukti' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        try {
            $path = $request->file('bukti')->store('bukti-transfer', 'public');

            $order->update([
                'bukti_transfer_path' => $path,
                'bukti_transfer_at' => now(),
            ]);
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Gagal mengunggah bukti transfer.']);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Bukti transfer untuk {$order->no_order} terkirim — menunggu verifikasi admin.",
        ]);

        return back();
    }
}
