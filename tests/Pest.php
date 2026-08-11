<?php

use App\Models\City;
use App\Models\District;
use App\Models\Province;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Response as InertiaResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| Helpers yang dipakai lintas test file.
|
*/

/**
 * Mock respons api.co.id: resolve desa + daftar ongkir.
 */ /** Mock respons Biteship Rates API. */
function fakeBiteshipApi(): void
{
    // Struktur respons asli Biteship v1/rates/couriers (pricing di root).
    Http::fake([
        'api.biteship.com/*' => Http::response([
            'success' => true,
            'pricing' => [
                [
                    'courier_code' => 'jne',
                    'courier_name' => 'JNE',
                    'courier_service_name' => 'Reguler',
                    'price' => 12000,
                    'duration' => '1 - 2 days',
                ],
            ],
        ]),
    ]);
}

function fakeShippingApi(): void
{
    // Regional API: urutan respons — (1) search nama kelurahan, (2) list kelurahan district.
    Http::fake([
        'use.api.co.id/regional/*' => Http::sequence()
            ->push([
                'is_success' => true,
                'data' => [[
                    'code' => '3573051002',
                    'name' => 'Merjosari',
                    'district' => 'LOWOKWARU',
                    'district_code' => '357305',
                    'is_courier_support' => true,
                ]],
            ])
            ->push([
                'is_success' => true,
                'data' => [
                    [
                        'code' => '3573051002',
                        'name' => 'Merjosari',
                        'district' => 'LOWOKWARU',
                        'district_code' => '357305',
                        'is_courier_support' => true,
                    ],
                    [
                        'code' => '3573051004',
                        'name' => 'Dinoyo',
                        'district' => 'LOWOKWARU',
                        'district_code' => '357305',
                        'is_courier_support' => true,
                    ],
                ],
            ]),
        'use.api.co.id/expedition/*' => Http::response([
            'is_success' => true,
            'data' => [
                'couriers' => [[
                    'courier_code' => 'JNE',
                    'courier_name' => 'JNE Express',
                    'price' => 12000,
                    'weight' => 1,
                    'estimation' => '1 - 2 days',
                ]],
            ],
        ]),
    ]);
}

/**
 * Buat data wilayah lokal (Provinsi → Kota → Kecamatan → Kelurahan) utk test
 * resolve ongkir — data ini dibutuhkan ShippingCostService sebelum sync API.
 */
function createLocalVillages(): void
{
    $province = Province::forceCreate(['code' => '35', 'name' => 'JAWA TIMUR']);
    $city = City::forceCreate([
        'code' => '3573',
        'province_code' => $province->code,
        'name' => 'KOTA MALANG',
    ]);
    $district = District::forceCreate([
        'code' => '3573050',
        'city_code' => $city->code,
        'name' => 'LOWOKWARU',
    ]);

    Village::forceCreate([
        'code' => '3573050001',
        'district_code' => $district->code,
        'name' => 'MERJOSARI',
    ]);

    Village::forceCreate([
        'code' => '3573050002',
        'district_code' => $district->code,
        'name' => 'DINOYO',
    ]);
}

/**
 * Ambil props dari response Inertia (JSON atau halaman render pertama).
 *
 * @return array<string, mixed>
 */
function inertiaProps(TestResponse $response): array
{
    $content = $response->getOriginalContent();

    if ($content instanceof InertiaResponse) {
        return $content->toArray()['props'] ?? [];
    }

    return $response->viewData('page')['props'] ?? [];
}
