<?php

return [
    /*
    |--------------------------------------------------------------------------
    | api.co.id — Cek Ongkos Kirim
    |--------------------------------------------------------------------------
    | https://docs.api.co.id/products/indonesia-expedition-cost
    |
    | origin_village_code: kode desa (10 digit) lokasi gudang/pengiriman toko.
    | Contoh: 3573050001 = Merjosari, Lowokwaru, Malang.
    */
    'key' => env('API_CO_ID_KEY'),
    'origin_village_code' => env('API_CO_ORIGIN_VILLAGE'),
    'base_url' => env('API_CO_BASE_URL', 'https://use.api.co.id'),
];
