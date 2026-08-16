<?php

use App\Models\Courier;
use App\Models\Setting;
use App\Services\RajaOngkirCostService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
    Setting::set('origin_postal_code', '65144');
});

function roItems(int $weightGrams = 1000): array
{
    return [[
        'name' => 'Buku Test',
        'value' => 50000,
        'quantity' => 1,
        'weight_grams' => $weightGrams,
    ]];
}

it('maps RajaOngkir response into the app shipping option shape', function (): void {
    Courier::create(['code' => 'jne', 'name' => 'JNE', 'is_active' => true, 'sort_order' => 0]);
    fakeRajaOngkirApi();

    $costs = app(RajaOngkirCostService::class)->costs('65144', roItems());

    expect($costs)->toHaveCount(2)
        ->and($costs[0]['courier_code'])->toBe('jne')
        ->and($costs[0]['service_code'])->toBe('REG')
        ->and($costs[0]['price'])->toBe(12000)
        ->and($costs[0]['courier_name'])->toContain('JNE')
        ->and($costs[0]['estimation'])->toBe('1-2')
        ->and($costs[1]['service_code'])->toBe('YES')
        ->and($costs[1]['price'])->toBe(25000);
});

it('calls the API once per courier and serves the rest from cache', function (): void {
    Courier::create(['code' => 'jne', 'name' => 'JNE', 'is_active' => true, 'sort_order' => 0]);
    fakeRajaOngkirApi();

    $service = app(RajaOngkirCostService::class);

    $first = $service->costs('65144', roItems());
    Http::assertSentCount(2); // 1 search (origin=tujuan 65144) + 1 calculate (jne)

    $second = $service->costs('65144', roItems());
    Http::assertSentCount(2);

    expect($second)->toBe($first);
});

it('only requests enabled couriers that RajaOngkir knows (cod excluded)', function (): void {
    Courier::create(['code' => 'wahana', 'name' => 'Wahana', 'is_active' => true, 'sort_order' => 0]);
    Courier::create(['code' => 'cod', 'name' => 'COD / Ambil Sendiri', 'is_active' => true, 'sort_order' => 0]);

    Http::fake([
        'rajaongkir.komerce.id/api/v1/destination/domestic-destination*' => Http::response([
            'meta' => ['message' => 'ok', 'code' => 200, 'status' => 'success'],
            'data' => [[
                'id' => 700114,
                'label' => 'Test 65144',
                'province_name' => 'JAWA TIMUR',
                'city_name' => 'KOTA MALANG',
                'district_name' => 'KLOJEN',
                'subdistrict_name' => 'BARENG',
                'zip_code' => '65144',
            ]],
        ]),
        'rajaongkir.komerce.id/api/v1/calculate/domestic-cost' => function (Request $request) {
            return Http::response([
                'meta' => ['message' => 'ok', 'code' => 200, 'status' => 'success'],
                'data' => $request['courier'] === 'wahana' ? [
                    ['name' => 'Wahana Express', 'code' => 'wahana', 'service' => 'REG', 'description' => 'Reguler', 'cost' => 14000, 'etd' => '2-3'],
                ] : [],
            ]);
        },
    ]);

    $costs = app(RajaOngkirCostService::class)->costs('65144', roItems());

    expect($costs)->toHaveCount(1)
        ->and($costs[0]['courier_code'])->toBe('wahana')
        ->and($costs[0]['price'])->toBe(14000);

    Http::assertSent(function ($request): bool {
        return $request->url() === config('rajaongkir.base_url').'/calculate/domestic-cost'
            && $request['courier'] === 'wahana';
    });
});

it('throws the RajaOngkir message when all couriers fail for the route', function (): void {
    Http::fake([
        'rajaongkir.komerce.id/api/v1/destination/domestic-destination*' => Http::response([
            'meta' => ['message' => 'ok', 'code' => 200, 'status' => 'success'],
            'data' => [[
                'id' => 700114,
                'label' => 'Test 65144',
                'province_name' => 'JAWA TIMUR',
                'city_name' => 'KOTA MALANG',
                'district_name' => 'KLOJEN',
                'subdistrict_name' => 'BARENG',
                'zip_code' => '65144',
            ]],
        ]),
        'rajaongkir.komerce.id/api/v1/calculate/domestic-cost' => Http::response([
            'meta' => ['message' => 'Calculate Domestic Shipping cost not found', 'code' => 400, 'status' => 'error'],
            'data' => null,
        ], 400),
    ]);

    expect(fn () => app(RajaOngkirCostService::class)->costs('65144', roItems()))
        ->toThrow(RuntimeException::class, 'Calculate Domestic Shipping cost not found');
});

it('throws a friendly error when the destination postal code is unknown', function (): void {
    Http::fake([
        'rajaongkir.komerce.id/api/v1/destination/domestic-destination*' => Http::response([
            'meta' => ['message' => 'Domestic Destinations Data not found', 'code' => 404, 'status' => 'error'],
            'data' => null,
        ], 404),
    ]);

    expect(fn () => app(RajaOngkirCostService::class)->costs('99999', roItems()))
        ->toThrow(RuntimeException::class, 'Domestic Destinations Data not found');
});

it('throws a friendly error when the origin postal code is not set', function (): void {
    Setting::set('origin_postal_code', null);

    Http::fake([
        'rajaongkir.komerce.id/*' => Http::response(['meta' => ['message' => 'ok', 'code' => 200, 'status' => 'success'], 'data' => []]),
    ]);

    expect(fn () => app(RajaOngkirCostService::class)->costs('65144', roItems()))
        ->toThrow(RuntimeException::class, 'Kode pos asal toko belum diatur');

    Http::assertNothingSent();
});

it('resolves the origin location using the postal code from settings', function (): void {
    Setting::set('origin_postal_code', '65151');

    Courier::create(['code' => 'jne', 'name' => 'JNE', 'is_active' => true, 'sort_order' => 0]);
    fakeRajaOngkirApi();

    $service = app(RajaOngkirCostService::class);

    $service->costs('60231', roItems());

    Http::assertSent(function ($request): bool {
        return str_starts_with($request->url(), config('rajaongkir.base_url').'/destination/domestic-destination')
            && $request['search'] === '65151';
    });
});

it('rounds the weight up to a 100g bucket for payload and cache', function (): void {
    Courier::create(['code' => 'jne', 'name' => 'JNE', 'is_active' => true, 'sort_order' => 0]);
    fakeRajaOngkirApi();

    $service = app(RajaOngkirCostService::class);

    // 350g → bucket 400g.
    $service->costs('65144', roItems(350));
    Http::assertSent(fn ($request) => $request->url() === config('rajaongkir.base_url').'/calculate/domestic-cost'
        && (int) $request['weight'] === 400);

    // 410g → bucket 500g (request baru).
    $service->costs('65144', roItems(410));
    Http::assertSent(fn ($request) => $request->url() === config('rajaongkir.base_url').'/calculate/domestic-cost'
        && (int) $request['weight'] === 500);

    // 380g → bucket 400g (cache hit — tidak ada request baru).
    Http::assertSentCount(3); // 1 search + 2 calculate
    $service->costs('65144', roItems(380));
    Http::assertSentCount(3);
});
