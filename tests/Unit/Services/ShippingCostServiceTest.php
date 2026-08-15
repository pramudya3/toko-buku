<?php

use App\Models\Courier;
use App\Models\Setting;
use App\Services\ShippingCostService;
use App\Support\StoreSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
    createLocalVillages();

    // Origin toko — service membaca dari tabel settings saja.
    Setting::set('origin_postal_code', '65144');
});

function biteshipItems(int $weightGrams = 1000): array
{
    return [[
        'name' => 'Buku Test',
        'value' => 50000,
        'quantity' => 1,
        'weight_grams' => $weightGrams,
    ]];
}

it('calls the API once and serves the rest from cache', function (): void {
    fakeBiteshipApi();

    $service = app(ShippingCostService::class);

    $first = $service->costs('65144', biteshipItems(1000));
    Http::assertSentCount(1);

    $second = $service->costs('65144', biteshipItems(1000));
    Http::assertSentCount(1);

    expect($second)->toBe($first)
        ->and($second[0]['price'])->toBe(12000);
});

it('uses a different cache entry for a different weight bucket', function (): void {
    fakeBiteshipApi();

    $service = app(ShippingCostService::class);

    $service->costs('65144', biteshipItems(1000));
    Http::assertSentCount(1);

    // Total weight berbeda → bucket berbeda → hit API baru.
    $service->costs('65144', biteshipItems(2000));
    Http::assertSentCount(2);

    // Kembali ke bucket pertama → dari cache.
    $service->costs('65144', biteshipItems(1000));
    Http::assertSentCount(2);
});

it('does not cache API errors', function (): void {
    Http::fake([
        'api.biteship.com/*' => Http::response(['success' => false], 500),
    ]);

    $service = app(ShippingCostService::class);

    expect(fn () => $service->costs('65144', biteshipItems()))
        ->toThrow(RuntimeException::class);

    // Key tidak ter-cache setelah error.
    $bucket = (int) ceil(1000 / 500) * 500;
    $key = 'ongkir:biteship:'.md5('65144:65144:'.$bucket.':'.implode(',', StoreSettings::enabledCourierCodes()));

    expect(Cache::get($key))->toBeNull();
});

it('serves costs from cache for the same postal code and weight across callers', function (): void {
    fakeBiteshipApi();

    $service = app(ShippingCostService::class);

    $service->costs('65144', biteshipItems(1500));
    Http::assertSentCount(1);

    // User berbeda — tujuan & bucket sama → dari cache, tanpa hit API.
    $service->costs('65144', biteshipItems(1500));
    Http::assertSentCount(1);

    // Tujuan beda → hit API baru.
    $service->costs('65145', biteshipItems(1500));
    Http::assertSentCount(2);
});

it('sends multiple items correctly', function (): void {
    fakeBiteshipApi();

    $service = app(ShippingCostService::class);

    $service->costs('65144', [
        ['name' => 'Buku A', 'value' => 100000, 'quantity' => 1, 'weight_grams' => 500],
        ['name' => 'Buku B', 'value' => 50000, 'quantity' => 2, 'weight_grams' => 300],
    ]);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return count($body['items']) === 2
            && $body['items'][0]['weight_grams'] === 500
            && $body['items'][1]['quantity'] === 2;
    });
});

it('only returns rates for enabled couriers and requests only them from the API', function (): void {
    Courier::create(['code' => 'wahana', 'name' => 'Wahana', 'is_active' => true, 'sort_order' => 0]);
    Courier::create(['code' => 'jne', 'name' => 'JNE', 'is_active' => false, 'sort_order' => 0]);
    Courier::create(['code' => 'cod', 'name' => 'COD / Ambil Sendiri', 'is_active' => true, 'sort_order' => 0]);

    Http::fake([
        'api.biteship.com/*' => Http::response([
            'success' => true,
            'pricing' => [
                ['courier_code' => 'jne', 'courier_name' => 'JNE', 'courier_service_code' => 'reg', 'courier_service_name' => 'Reguler', 'price' => 12000, 'duration' => '1 - 2 days'],
                ['courier_code' => 'wahana', 'courier_name' => 'Wahana', 'courier_service_code' => 'reg', 'courier_service_name' => 'Reguler', 'price' => 14000, 'duration' => '2 - 3 days'],
            ],
        ]),
    ]);

    $costs = app(ShippingCostService::class)->costs('65144', biteshipItems());

    expect($costs)->toHaveCount(1)
        ->and($costs[0]['courier_code'])->toBe('wahana')
        ->and($costs[0]['price'])->toBe(14000);

    // Pseudo-kurir 'cod' tidak boleh ikut dikirim ke Biteship (ditolak 400).
    Http::assertSent(function ($request): bool {
        return $request['couriers'] === 'wahana';
    });
});
