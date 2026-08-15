<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\StoreSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Perhitungan ongkos kirim via Biteship (Rates API).
 *
 * Endpoint: POST {base}/v1/rates/couriers
 * Auth: Authorization: Bearer {key}
 * Body: origin_postal_code, destination_postal_code, couriers, items[]
 *
 * Keunggulan vs api.co.id:
 * - Tidak butuh resolve kode desa (pakai kode pos langsung).
 * - 1 request per cek, bayar per hit (Rp 5/hit), tanpa langganan.
 *
 * @see https://biteship.com/id/docs/api/rates/overview
 */
final class ShippingCostService
{
    /** Cache TTL 24 jam. */
    private const COSTS_TTL_SECONDS = 24 * 60 * 60;

    /** Cache hasil kosong 1 jam. */
    private const EMPTY_COSTS_TTL_SECONDS = 60 * 60;

    /**
     * Hitung ongkir ke kode pos tujuan untuk daftar item (buku + qty).
     *
     * @param  array<int, array{name: string, value: int, quantity: int, weight_grams: int}>  $items
     * @return array<int, array{courier_code: string, courier_name: string, service_code: string, price: int, estimation: string|null}>
     */
    public function costs(string $destinationPostalCode, array $items): array
    {
        if ($items === []) {
            throw new RuntimeException('Tidak ada item untuk dihitung ongkirnya.');
        }

        $totalWeight = (int) collect($items)->sum(fn (array $item): int => $item['weight_grams'] * $item['quantity']);
        // Berat dibulatkan ke atas ke bucket 100g — dipakai untuk payload &
        // cache key (perubahan qty dalam bucket sama = cache hit, hemat hit).
        $bucket = (int) (ceil($totalWeight / 100) * 100);

        // Hanya kurir aktif (Settings → Ekspedisi) yang ditampilkan;
        // hash daftar aktif masuk cache key supaya cache ikut valid saat
        // admin menonaktifkan/mengaktifkan ekspedisi.
        $enabled = StoreSettings::enabledCourierCodes();

        $key = 'ongkir:biteship:'.md5(
            $this->originPostalCode().':'.$destinationPostalCode.':'.$bucket.':'.implode(',', $enabled),
        );

        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $costs = $this->fetchFromApi($destinationPostalCode, $items, $enabled);

        Cache::put(
            $key,
            $costs,
            $costs === [] ? self::EMPTY_COSTS_TTL_SECONDS : self::COSTS_TTL_SECONDS,
        );

        return $costs;
    }

    /**
     * @param  array<int, array{name: string, value: int, quantity: int, weight_grams: int}>  $items
     * @param  list<string>  $enabledCouriers
     * @return array<int, array{courier_code: string, courier_name: string, service_code: string, price: int, estimation: string|null}>
     */
    private function fetchFromApi(string $destinationPostalCode, array $items, array $enabledCouriers = []): array
    {
        // Hanya kode courier yang benar-benar dikenal Biteship — pseudo-kurir
        // aplikasi (mis. 'cod') ditolak Biteship dengan HTTP 400.
        $biteshipCodes = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('biteship.couriers')),
        )));

        $requested = $enabledCouriers !== []
            ? array_values(array_intersect($enabledCouriers, $biteshipCodes))
            : [];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.(Setting::getSecret('biteship_api_key') ?: config('biteship.key')),
                'Accept' => 'application/json',
            ])->timeout(10)
                ->post(config('biteship.base_url').'/v1/rates/couriers', [
                    'origin_postal_code' => (int) $this->originPostalCode(),
                    'destination_postal_code' => (int) $destinationPostalCode,
                    // Biteship menerima string dipisah koma, bukan array.
                    'couriers' => $requested !== []
                        ? implode(',', $requested)
                        : config('biteship.couriers'),
                    'items' => array_values($items),
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Layanan ongkir sedang tidak tersedia, coba lagi nanti.');
        }

        if ($response->failed()) {
            // Diagnostik: body penolakan Biteship (tanpa API key) untuk
            // melacak request ongkir yang ditolak (rute/param tidak valid).
            Log::warning('Biteship rates gagal', [
                'destination_postal_code' => $destinationPostalCode,
                'couriers' => $requested !== [] ? implode(',', $requested) : config('biteship.couriers'),
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new RuntimeException('Gagal menghitung ongkos kirim ('.$response->status().').');
        }

        $pricing = $response->json('pricing');

        if (! is_array($pricing)) {
            return [];
        }

        return array_values(array_filter(array_map(function (array $rate) use ($enabledCouriers): ?array {
            if (! isset($rate['courier_code'], $rate['courier_name'], $rate['price'])) {
                return null;
            }

            // Pertahanan ganda: Biteship bisa saja tetap membalas kurir lain.
            if ($enabledCouriers !== [] && ! in_array($rate['courier_code'], $enabledCouriers, true)) {
                return null;
            }

            // Biteship memberi per layanan (Reguler, YES, dll.) — tampilkan semua.
            $label = ($rate['courier_service_name'] ?? '') !== ''
                ? $rate['courier_name'].' '.$rate['courier_service_name']
                : $rate['courier_name'];

            return [
                'courier_code' => $rate['courier_code'],
                'courier_name' => $label,
                'service_code' => $rate['courier_service_code'] ?? '',
                'price' => (int) $rate['price'],
                'estimation' => $rate['duration'] ?? null,
            ];
        }, $pricing)));
    }

    /**
     * @param  array<int, array{name: string, value: int, quantity: int, weight_grams: int}>  $items
     */
    public function totalWeightGrams(array $items): int
    {
        return (int) collect($items)->sum(fn (array $item): int => $item['weight_grams'] * $item['quantity']);
    }

    private function originPostalCode(): string
    {
        $postal = Setting::get('origin_postal_code');

        if (! is_string($postal) || trim($postal) === '') {
            throw new RuntimeException('Kode pos asal toko belum diatur — isi di Pengaturan → Lembaga.');
        }

        return trim($postal);
    }
}
