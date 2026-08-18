<?php

namespace App\Http\Controllers;

/**
 * Halaman order milik customer (storefront paralel "Pustaka Cahaya
 * Peradaban" — desain proto-d) — /pcd/pesanan-saya.
 *
 * Seluruh logika (order milik user, invoice, upload bukti) diwarisi dari
 * MyOrderController — hanya komponen Inertia yang berbeda.
 */
class MyOrderPcdController extends MyOrderController
{
    protected function page(string $name): string
    {
        return "storefront-pcd/{$name}";
    }
}
