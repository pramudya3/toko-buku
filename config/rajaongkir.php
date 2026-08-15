<?php

return [
    /*
    |--------------------------------------------------------------------------
    | RajaOngkir (Komerce) — Cek Ongkos Kirim
    |--------------------------------------------------------------------------
    | https://rajaongkir.com/docs/shipping-cost
    |
    | Endpoint:
    | - GET  {base}/destination/domestic-destination?search={kode_pos}
    | - POST {base}/calculate/domestic-cost
    | Auth: header `key: API_KEY`
    */

    'key' => env('RAJAONGKIR_API_KEY'),

    'base_url' => env('RAJAONGKIR_BASE_URL', 'https://rajaongkir.komerce.id/api/v1'),

    /*
    | Kode courier yang dikenal RajaOngkir untuk cek ongkir domestik
    | (daftar 3PL: https://rajaongkir.com/docs/shipping-cost/getting_started/courier_availability).
    | Catatan: IDExpress = 'ide' (di Biteship = 'idexpress').
    */
    'couriers' => env(
        'RAJAONGKIR_COURIERS',
        'jne,sicepat,ide,sap,ninja,jnt,tiki,wahana,pos,sentral,lion,rex',
    ),
];
