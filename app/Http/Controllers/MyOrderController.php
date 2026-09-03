<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\OrderStatus;
use App\Http\Requests\UploadBuktiRequest;
use App\Models\BankAccount;
use App\Models\Order;
use App\Models\Setting;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            // Filter status transaksi — hanya nilai enum yang valid.
            ->when($request->filled('status'), function ($query) use ($request): void {
                $status = $request->string('status')->toString();

                if (OrderStatus::tryFrom($status) !== null) {
                    $query->where('status', $status);
                }
            })
            // Quick-filter pembayaran: chip "Belum Dibayar" di UI.
            ->when($request->boolean('belum_dibayar'), fn ($query) => $query->where('payment_status', 'menunggu'))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render($this->page('MyOrders'), [
            'orders' => $orders,
            'statusOptions' => OrderStatus::options(),
            'filters' => $request->only(['status', 'belum_dibayar']),
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
     *
     * Boleh diulang selama payment_status masih menunggu (salah kirim /
     * gambar buram) — file lama dihapus agar tidak menumpuk orphan.
     */
    public function uploadBukti(UploadBuktiRequest $request, Order $order): RedirectResponse
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

        $validated = $request->validated();

        try {
            $disk = Storage::disk('public');
            $oldPath = $order->bukti_transfer_path;
            $isReplace = $oldPath !== null;
            $path = $request->file('bukti')->store('bukti-transfer', 'public');

            // Ganti bukti → hapus file lama supaya storage bersih & admin
            // tidak keliru membuka bukti versi lama.
            if ($isReplace && $disk->exists($oldPath)) {
                $disk->delete($oldPath);
            }

            $order->update([
                'bukti_transfer_path' => $path,
                'bukti_transfer_at' => now(),
            ]);

            ActivityLogger::log(
                $isReplace ? ActivityAction::OrderBuktiUpdate : ActivityAction::OrderBuktiUpload,
                ($isReplace ? 'Bukti transfer diganti' : 'Bukti transfer diunggah').' untuk order '.$order->no_order,
                subject: $order,
            );
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
