<?php

use App\Services\ShippingCostService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
    createLocalVillages();
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
    $key = 'ongkir:biteship:'.md5(config('biteship.origin_postal_code').':65144:'.$bucket);

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
