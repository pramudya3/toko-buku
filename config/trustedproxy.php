<?php

use Illuminate\Http\Request;

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Aplikasi berjalan di belakang proxy (nginx host / Cloudflare Tunnel).
    | Trust semua proxy — port container hanya bind ke 127.0.0.1, sehingga
    | hanya nginx di host yang bisa menjangkau aplikasi.
    |
    */

    'proxies' => env('TRUSTED_PROXIES', '*'),

    'headers' => Request::HEADER_X_FORWARDED_FOR
        | Request::HEADER_X_FORWARDED_HOST
        | Request::HEADER_X_FORWARDED_PORT
        | Request::HEADER_X_FORWARDED_PROTO
        | Request::HEADER_X_FORWARDED_PREFIX,

];
