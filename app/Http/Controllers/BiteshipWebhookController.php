<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Models\Order;
use App\Models\Setting;
use App\Services\OrderStatusService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook Biteship — sinkronisasi status pengiriman secara otomatis:
 * picked/in_transit → order "dikirim", delivered → order "selesai"
 * (cash flow tercatat otomatis via OrderStatusService).
 *
 * @see https://biteship.com/id/docs/api/webhook/overview
 */
class BiteshipWebhookController extends Controller
{
    public function __construct(private readonly OrderStatusService $statusService) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Ping verifikasi saat webhook dipasang di dashboard Biteship —
        // body kosong & tanpa signature → cukup balas OK.
        if (trim($request->getContent()) === '') {
            return response()->json(['success' => true]);
        }

        if (! $this->signatureIsValid($request)) {
            return response()->json(['error' => 'Invalid signature.'], 403);
        }

        $payload = $request->input();

        $order = isset($payload['order_id'])
            ? Order::where('biteship_order_id', $payload['order_id'])->first()
            : null;

        if ($order === null) {
            return response()->json(['error' => 'Order not found.'], 404);
        }

        $biteshipStatus = is_string($payload['status'] ?? null) ? $payload['status'] : null;

        // Perubahan harga (berat aktual berbeda dari estimasi) → sinkron ongkir
        // & total (total kolom tersimpan: total = subtotal produk + ongkir).
        if (($payload['event'] ?? null) === 'order.price' && isset($payload['shippment_fee'])) {
            $newShipping = (int) $payload['shippment_fee'];
            $productSubtotal = $order->total - $order->shipping_cost;

            ActivityLogger::log(
                ActivityAction::OrderStatus,
                "Ongkir {$order->no_order} disesuaikan Biteship: "
                    .$order->shipping_cost.' → '.$newShipping,
                $order,
                ['shipping_cost_from' => $order->shipping_cost, 'shipping_cost_to' => $newShipping],
            );

            $order->update([
                'shipping_cost' => $newShipping,
                'total' => $productSubtotal + $newShipping,
            ]);
        }

        if ($biteshipStatus !== null) {
            $order->update([
                'biteship_status' => $biteshipStatus,
                // Link tracking kurir — dipakai tombol "Lacak" di Pesanan Saya.
                'biteship_courier_link' => $payload['courier_link'] ?? $order->biteship_courier_link,
                // AWB bisa terbit asinkron (via event status kurir).
                'awb' => $order->awb === null
                    ? ($payload['courier_waybill_id'] ?? null)
                    : $order->awb,
            ]);
        }

        // Status yang memicu transisi status order aplikasi.
        if ($biteshipStatus !== null) {
            $this->statusService->applyBiteshipStatus($order, $biteshipStatus);
        }

        // Status problematik tidak bisa ditransisikan otomatis — catat agar
        // admin mengetahuinya (mis. kurir membatalkan pengiriman).
        if (in_array($biteshipStatus, ['cancelled', 'returned', 'on_hold', 'expired', 'error', 'disposed'], true)) {
            ActivityLogger::log(
                ActivityAction::OrderStatus,
                "Pengiriman Biteship {$order->no_order}: status {$biteshipStatus} — perlu tindakan admin.",
                $order,
                ['biteship_status' => $biteshipStatus],
            );
        }

        return response()->json(['success' => true]);
    }

    private function signatureIsValid(Request $request): bool
    {
        $signature = $request->header('X-Signature');

        if (! is_string($signature) || $signature === '') {
            return false;
        }

        $secret = Setting::getSecret('biteship_webhook_secret')
            ?: config('biteship.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $computed = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($computed, $signature);
    }
}
