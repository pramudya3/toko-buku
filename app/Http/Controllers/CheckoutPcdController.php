<?php

namespace App\Http\Controllers;

/**
 * Checkout storefront paralel "Pustaka Cahaya Peradaban" (desain proto-d).
 *
 * Seluruh logika (keranjang session, validasi, ongkir, pembuatan order)
 * diwarisi dari CheckoutController — hanya komponen Inertia & route sukses
 * yang berbeda.
 */
class CheckoutPcdController extends CheckoutController
{
    protected function page(string $name): string
    {
        return "storefront-pcd/{$name}";
    }

    protected function successRoute(): string
    {
        return 'pcd.checkout.success';
    }
}
