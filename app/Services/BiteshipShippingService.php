<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use App\Support\StoreSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Booking pengiriman via Biteship (Orders & Pickups API).
 *
 * Endpoint:
 * - POST {base}/v1/orders        → terbit AWB + label (saldo terpotong)
 * - POST {base}/v1/orders/{id}/cancel
 * - GET  {base}/v1/orders/{id}   → sinkronisasi status
 * - POST {base}/v1/pickups       → jadwalkan penjemputan kurir
 * - GET  {base}/v1/couriers      → daftar courier & layanan
 *
 * @see https://biteship.com/id/docs/api/orders/overview
 */
final class BiteshipShippingService
{
    /** Cache daftar courier 24 jam. */
    private const COURIERS_TTL_SECONDS = 24 * 60 * 60;

    /** Berat default per buku bila `berat_gr` tidak tersedia (gram). */
    private const DEFAULT_WEIGHT_GRAMS = 300;

    /**
     * Buat pengiriman (booking) → AWB + label terbit, saldo Biteship terpotong.
     *
     * @return array<string, mixed> field biteship dari response
     */
    public function createOrder(Order $order, string $courierCode, ?string $serviceCode = null, string $collectionMethod = 'pickup'): array
    {
        $this->assertShippable($order);
        $this->assertOriginComplete();

        $items = $order->items->map(fn ($item): array => [
            'name' => $item->judul_snapshot,
            'sku' => $item->book->kode_sku ?? '',
            'quantity' => $item->qty,
            'weight_grams' => $item->book->berat_gr ?? self::DEFAULT_WEIGHT_GRAMS,
            'value' => $item->price_final,
        ])->values()->all();

        $payload = [
            'origin_contact_name' => Setting::get('store_nama_lembaga', ''),
            'origin_contact_phone' => Setting::get('store_telepon', ''),
            'origin_contact_email' => Setting::get('store_email', ''),
            'origin_address' => Setting::get('store_alamat', ''),
            'origin_postal_code' => (int) $this->originPostalCode(),
            'origin_collection_method' => $collectionMethod === 'drop_off' ? 'drop_off' : 'pickup',
            'destination_contact_name' => $order->nama_pembeli,
            'destination_contact_phone' => $order->no_hp,
            'destination_contact_email' => $order->email_pembeli ?? '',
            'destination_address' => $this->fullAddress($order),
            'destination_postal_code' => (int) $order->kode_pos,
            'courier_company' => $courierCode,
            'courier_type' => $serviceCode ?? '',
            'delivery_type' => 'now',
            'reference_id' => $order->no_order,
            'metadata' => ['order_id' => $order->id],
            'items' => $items,
        ];

        $response = $this->request('post', '/v1/orders', $payload, message: 'Gagal membuat pengiriman.');

        $result = $response['data'] ?? $response;

        $order->update([
            'biteship_order_id' => $result['id'] ?? null,
            'awb' => $result['waybill_id'] ?? null,
            'biteship_label_url' => $result['label_url'] ?? null,
            'biteship_status' => $result['status'] ?? null,
            'biteship_courier_link' => $result['courier_link'] ?? $order->biteship_courier_link,
            'courier_service_code' => $serviceCode,
        ]);

        return $result;
    }

    /**
     * Batalkan pengiriman di Biteship (wajib sebelum order boleh batal).
     *
     * @return array<string, mixed>
     */
    public function cancelOrder(Order $order): array
    {
        $this->assertBooked($order);

        $response = $this->request('post', "/v1/orders/{$order->biteship_order_id}/cancel", [], message: 'Gagal membatalkan pengiriman.');

        $order->update(['biteship_status' => 'cancelled']);

        return $response;
    }

    /**
     * Jadwalkan penjemputan kurir (pickup) — alamat gudang dari settings.
     *
     * @return array<string, mixed>
     */
    public function requestPickup(Order $order, ?string $pickupDate = null, ?string $pickupTime = null): array
    {
        $this->assertBooked($order);
        $this->assertOriginComplete();

        $payload = array_filter([
            'courier' => $order->ekspedisi,
            'pickup_address' => Setting::get('store_alamat', ''),
            'pickup_contact_name' => Setting::get('store_nama_lembaga', ''),
            'pickup_contact_phone' => Setting::get('store_telepon', ''),
            'pickup_date' => $pickupDate,
            'pickup_time' => $pickupTime,
            'shipping_orders' => [$order->biteship_order_id],
        ], fn ($value): bool => $value !== null);

        return $this->request('post', '/v1/pickups', $payload, message: 'Gagal menjadwalkan penjemputan.');
    }

    /**
     * Sinkronkan status pengiriman dari Biteship.
     *
     * @return array<string, mixed>
     */
    public function retrieveOrder(Order $order): array
    {
        $this->assertBooked($order);

        $response = $this->request('get', "/v1/orders/{$order->biteship_order_id}", message: 'Gagal memuat status pengiriman.');

        $data = $response['data'] ?? $response;

        $order->update([
            'awb' => $data['waybill_id'] ?? $order->awb,
            'biteship_label_url' => $data['label_url'] ?? $order->biteship_label_url,
            'biteship_status' => $data['status'] ?? $order->biteship_status,
            'biteship_courier_link' => $data['courier_link'] ?? $order->biteship_courier_link,
        ]);

        return $data;
    }

    /**
     * Daftar courier + layanan dari Biteship (di-cache 24 jam) untuk
     * dropdown pilihan courier/service saat booking.
     *
     * @return array<int, array{courier_code: string, courier_name: string, service_code: string, service_name: string}>
     */
    public function courierServices(): array
    {
        $enabled = StoreSettings::enabledCourierCodes();

        return Cache::remember('biteship:couriers:'.implode(',', $enabled), self::COURIERS_TTL_SECONDS, function () use ($enabled): array {
            $response = $this->request('get', '/v1/couriers', message: 'Gagal memuat daftar ekspedisi.');

            $couriers = $response['couriers'] ?? [];

            return array_values(array_filter(array_map(function (array $courier) use ($enabled): ?array {
                if (! isset($courier['courier_code'], $courier['courier_name'])) {
                    return null;
                }

                // Hanya kurir aktif (Settings → Ekspedisi).
                if ($enabled !== [] && ! in_array($courier['courier_code'], $enabled, true)) {
                    return null;
                }

                return [
                    'courier_code' => $courier['courier_code'],
                    'courier_name' => $courier['courier_name'],
                    'service_code' => $courier['courier_service_code'] ?? '',
                    'service_name' => $courier['courier_service_name'] ?? '',
                ];
            }, $couriers)));
        });
    }

    /**
     * Order hanya boleh dibooking bila: channel website, sudah lunas,
     * dan masih dalam tahap diproses (belum dikirim).
     */
    private function assertShippable(Order $order): void
    {
        if ($order->sumber_pembelian !== 'website') {
            throw new RuntimeException('Pengiriman kurir hanya untuk order website.');
        }

        if ($order->status->value !== 'diproses') {
            throw new RuntimeException('Pengiriman hanya bisa dibuat saat order berstatus diproses.');
        }
    }

    private function assertBooked(Order $order): void
    {
        if (! $order->biteship_order_id) {
            throw new RuntimeException('Order belum memiliki pengiriman Biteship.');
        }
    }

    /**
     * Data origin (toko) wajib lengkap sebelum booking/pickup — Biteship
     * menolak payload dengan pesan Inggris yang membingungkan bila kosong.
     */
    private function assertOriginComplete(): void
    {
        $missing = [];

        if (! is_string(Setting::get('store_telepon')) || Setting::get('store_telepon') === '') {
            $missing[] = 'nomor telepon toko (Pengaturan → Lembaga)';
        }

        if (! is_string(Setting::get('store_alamat')) || Setting::get('store_alamat') === '') {
            $missing[] = 'alamat toko (Pengaturan → Lembaga)';
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'Lengkapi '.implode(' dan ', $missing).' sebelum membuat pengiriman.',
            );
        }
    }

    private function fullAddress(Order $order): string
    {
        $parts = array_filter([
            $order->alamat,
            $order->kelurahan,
            $order->kecamatan,
            $order->kabupaten_kota,
            $order->provinsi,
        ]);

        return implode(', ', $parts);
    }

    private function originPostalCode(): string
    {
        $postal = Setting::get('origin_postal_code');

        if (! is_string($postal) || trim($postal) === '') {
            throw new RuntimeException('Kode pos asal toko belum diatur — isi di Pengaturan → Lembaga.');
        }

        return trim($postal);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = [], string $message = ''): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.(Setting::getSecret('biteship_api_key') ?: config('biteship.key')),
                'Accept' => 'application/json',
            ])->timeout(10)->retry(2, 200, throw: false)
                ->{$method}(config('biteship.base_url').$path, $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Layanan Biteship sedang tidak tersedia, coba lagi nanti.');
        }

        if ($response->failed()) {
            $error = $response->json('error') ?? $response->json('message') ?? '';

            // Diagnostik: body respons penolakan Biteship (tanpa API key) —
            // untuk melacak penolakan pickup/booking yang tidak jelas.
            Log::warning('Biteship request gagal', [
                'method' => strtoupper($method),
                'path' => $path,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            // Saldo tidak cukup / courier tidak aktif — tampilkan pesan dari Biteship.
            if (is_string($error) && $error !== '') {
                throw new RuntimeException($error);
            }

            throw new RuntimeException(($message ?: 'Gagal menghubungi Biteship').' ('.$response->status().').');
        }

        return $response->json() ?? [];
    }
}
