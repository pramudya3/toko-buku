<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Biteship — Cek Ongkos Kirim (pay-per-use Rp 5/hit)
    |--------------------------------------------------------------------------
    | https://biteship.com/id/docs/api/rates/overview
    |
    | Menggunakan kode pos (bukan kode desa) — lebih sederhana dari api.co.id.
    | Tidak butuh resolve kode wilayah lewat API terpisah.
    */
    'key' => env('BITESHIP_API_KEY'),
    /*
    | Kode pos gudang disimpan di tabel settings (bisa diubah via Admin > Pengaturan).
    | Gunakan helper biteship_origin() untuk membaca nilai runtime.
    */
    'origin_postal_code' => env('BITESHIP_ORIGIN_POSTAL_CODE', '65144'),
    'base_url' => env('BITESHIP_BASE_URL', 'https://api.biteship.com'),
    'couriers' => env('BITESHIP_COURIERS', 'jne,sicepat,jnt,anteraja,idexpress,lion,ninja,paxel,sap,wahana'),
];
