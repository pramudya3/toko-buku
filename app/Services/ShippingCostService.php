<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
     * @return array<int, array{courier_code: string, courier_name: string, price: int, estimation: string|null}>
     */
    public function costs(string $destinationPostalCode, array $items): array
    {
        if ($items === []) {
            throw new RuntimeException('Tidak ada item untuk dihitung ongkirnya.');
        }

        $totalWeight = (int) collect($items)->sum(fn (array $item): int => $item['weight_grams'] * $item['quantity']);
        $bucket = (int) ceil($totalWeight / 500) * 500;

        $key = 'ongkir:biteship:'.md5(
            $this->originPostalCode().':'.$destinationPostalCode.':'.$bucket,
        );

        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $costs = $this->fetchFromApi($destinationPostalCode, $items);

        Cache::put(
            $key,
            $costs,
            $costs === [] ? self::EMPTY_COSTS_TTL_SECONDS : self::COSTS_TTL_SECONDS,
        );

        return $costs;
    }

    /**
     * @param  array<int, array{name: string, value: int, quantity: int, weight_grams: int}>  $items
     * @return array<int, array{courier_code: string, courier_name: string, price: int, estimation: string|null}>
     */
    private function fetchFromApi(string $destinationPostalCode, array $items): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.(Setting::getSecret('biteship_api_key') ?: config('biteship.key')),
                'Accept' => 'application/json',
            ])->timeout(10)
                ->post(config('biteship.base_url').'/v1/rates/couriers', [
                    'origin_postal_code' => (int) $this->originPostalCode(),
                    'destination_postal_code' => (int) $destinationPostalCode,
                    'couriers' => config('biteship.couriers'),
                    'items' => array_values($items),
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Layanan ongkir sedang tidak tersedia, coba lagi nanti.');
        }

        if ($response->failed()) {
            throw new RuntimeException('Gagal menghitung ongkos kirim ('.$response->status().').');
        }

        $pricing = $response->json('pricing');

        if (! is_array($pricing)) {
            return [];
        }

        return array_values(array_filter(array_map(function (array $rate): ?array {
            if (! isset($rate['courier_code'], $rate['courier_name'], $rate['price'])) {
                return null;
            }

            // Biteship memberi per layanan (Reguler, YES, dll.) — tampilkan semua.
            $label = ($rate['courier_service_name'] ?? '') !== ''
                ? $rate['courier_name'].' '.$rate['courier_service_name']
                : $rate['courier_name'];

            return [
                'courier_code' => $rate['courier_code'],
                'courier_name' => $label,
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
        return Setting::get('origin_postal_code') ?: config('biteship.origin_postal_code');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.(Setting::getSecret('biteship_api_key') ?: config('biteship.key')),
                'Accept' => 'application/json',
            ])->timeout(10)
                ->get(config('biteship.base_url').$path, $query);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Layanan ongkir sedang tidak tersedia, coba lagi nanti.');
        }

        if ($response->failed()) {
            throw new RuntimeException('Gagal menghubungi layanan ongkir ('.$response->status().').');
        }

        return $response->json() ?? [];
    }
}
