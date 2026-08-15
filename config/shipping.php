<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ekspedisi
    |--------------------------------------------------------------------------
    |
    | Daftar ekspedisi yang tersedia saat admin mengisi ongkir final.
    |
    */

    'couriers' => [
        'jne' => 'JNE',
        'jnt' => 'J&T Express',
        'sicepat' => 'SiCepat',
        'anteraja' => 'AnterAja',
        'ninja' => 'Ninja Express',
        'wahana' => 'Wahana',
        'pos' => 'POS Indonesia',
        'grab' => 'Grab Express',
        'gojek' => 'GoSend',
        'cod' => 'COD / Ambil Sendiri',
    ],

    /*
    |--------------------------------------------------------------------------
    | Link Tracking Manual
    |--------------------------------------------------------------------------
    |
    | Template URL tracking untuk pengiriman manual. Placeholder {awb} diganti
    | nomor resi. Dipakai untuk mengisi link Lacak & tombol di Pesanan Saya.
    |
    */

    'tracking_url_template' => env(
        'SHIPPING_TRACKING_URL_TEMPLATE',
        'https://www.wahana.com/lacak-kiriman?noresi={awb}',
    ),

];
