<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\StoreSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cek ongkos kirim via RajaOngkir API V2 (Komerce).
 *
 * @see https://rajaongkir.com/docs/shipping-cost
 *
 * - Lokasi (origin/destination) di-resolve dari kode pos via
 *   GET {base}/destination/domestic-destination?search={postal}
 * - Tarif per kurir: POST {base}/calculate/domestic-cost
 * - Auth: header `key: API_KEY`
 */
final class RajaOngkirCostService
{
    /** Cache lokasi (kode pos → id) — data statis. */
    private const LOCATION_TTL_SECONDS = 7 * 24 * 60 * 60;

    /** Cache tarif 24 jam. */
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

        $originId = $this->resolveLocationId($this->originPostalCode(), 'asal');
        $destinationId = $this->resolveLocationId($destinationPostalCode, 'tujuan');

        $enabled = array_values(array_intersect(
            StoreSettings::enabledCourierCodes(),
            $this->knownCourierCodes(),
        ));

        // Berat dibulatkan ke atas ke bucket 100g — dipakai untuk payload &
        // cache key. Perubahan qty dalam bucket yang sama = cache hit (hemat
        // hit API); round up = tarif konservatif (tidak pernah under-charge).
        $weight = (int) (ceil($this->totalWeightGrams($items) / 100) * 100);

        $key = 'ongkir:rajaongkir:'.md5(
            $originId.':'.$destinationId.':'.$weight.':'.implode(',', $enabled),
        );

        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $costs = $this->fetchFromApi($originId, $destinationId, $weight, $enabled);

        Cache::put(
            $key,
            $costs,
            $costs === [] ? self::EMPTY_COSTS_TTL_SECONDS : self::COSTS_TTL_SECONDS,
        );

        return $costs;
    }

    /**
     * @param  array<int, array{name: string, value: int, quantity: int, weight_grams: int}>  $items
     */
    public function totalWeightGrams(array $items): int
    {
        return max(1, (int) collect($items)->sum(fn (array $item): int => $item['weight_grams'] * $item['quantity']));
    }

    /**
     * Resolve kode pos → id lokasi RajaOngkir (search by postal code).
     */
    private function resolveLocationId(string $postalCode, string $label): int
    {
        $key = 'rajaongkir:loc:'.md5($postalCode);

        return (int) Cache::remember($key, self::LOCATION_TTL_SECONDS, function () use ($postalCode, $label): int {
            $response = $this->http()
                ->get(config('rajaongkir.base_url').'/destination/domestic-destination', [
                    'search' => $postalCode,
                    'limit' => 5,
                    'offset' => 0,
                ]);

            if ($response->failed()) {
                $message = $this->errorMessage($response->json());

                throw new RuntimeException($message !== null
                    ? $message
                    : 'Gagal mencari lokasi RajaOngkir ('.$response->status().').');
            }

            $data = $response->json('data');

            $match = collect(is_array($data) ? $data : [])
                ->first(fn (array $row): bool => (string) ($row['zip_code'] ?? '') === $postalCode);

            if (! is_array($match) || ! isset($match['id'])) {
                throw new RuntimeException("Kode pos {$label} tidak dikenali RajaOngkir — cek kembali alamat.");
            }

            return (int) $match['id'];
        });
    }

    /**
     * @param  list<string>  $courierCodes
     * @return array<int, array{courier_code: string, courier_name: string, service_code: string, price: int, estimation: string|null}>
     */
    private function fetchFromApi(int $originId, int $destinationId, int $weight, array $courierCodes): array
    {
        $results = [];
        $firstError = null;

        foreach ($courierCodes as $courierCode) {
            try {
                $response = $this->http()
                    ->asForm()
                    ->post(config('rajaongkir.base_url').'/calculate/domestic-cost', [
                        'origin' => $originId,
                        'destination' => $destinationId,
                        'weight' => $weight,
                        'courier' => $courierCode,
                    ]);
            } catch (ConnectionException $e) {
                throw new RuntimeException('Layanan ongkir sedang tidak tersedia, coba lagi nanti.');
            }

            if ($response->failed()) {
                $message = $this->errorMessage($response->json());

                Log::warning('RajaOngkir calculate gagal', [
                    'courier' => $courierCode,
                    'origin' => $originId,
                    'destination' => $destinationId,
                    'weight' => $weight,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                // 400 "not found" = kurir tak melayani rute — catat error
                // pertama; kalau SEMUA kurir gagal, error ditampilkan.
                $firstError ??= $message ?? 'Gagal menghitung ongkos kirim ('.$response->status().').';

                continue;
            }

            foreach ((array) ($response->json('data') ?? []) as $service) {
                if (! isset($service['code'], $service['service'], $service['cost'])) {
                    continue;
                }

                // Label layanan: nama kurir + layanan. Deskripsi RajaOngkir bisa
                // berupa kalimat panjang (Wahana) → pakai kode layanan saja.
                $serviceName = (string) ($service['description'] ?? '');
                if (mb_strlen($serviceName) > 30) {
                    $serviceName = (string) $service['service'];
                }

                $results[] = [
                    'courier_code' => (string) $service['code'],
                    'courier_name' => trim(((string) ($service['name'] ?? '')).' '.$serviceName),
                    'service_code' => (string) $service['service'],
                    'price' => (int) $service['cost'],
                    'estimation' => isset($service['etd']) ? (string) $service['etd'] : null,
                ];
            }
        }

        // Semua kurir gagal → tampilkan error RajaOngkir.
        if ($results === [] && $firstError !== null) {
            throw new RuntimeException($firstError);
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    private function knownCourierCodes(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('rajaongkir.couriers')),
        )));
    }

    private function originPostalCode(): string
    {
        $postal = Setting::get('origin_postal_code');

        if (! is_string($postal) || trim($postal) === '') {
            throw new RuntimeException('Kode pos asal toko belum diatur — isi di Pengaturan → Lembaga.');
        }

        return trim($postal);
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders([
            'key' => (string) (config('rajaongkir.key') ?? ''),
            'Accept' => 'application/json',
        ])->timeout(15);
    }

    /**
     * Ambil pesan error dari struktur meta RajaOngkir.
     */
    private function errorMessage(?array $body): ?string
    {
        $message = $body['meta']['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : null;
    }
}
