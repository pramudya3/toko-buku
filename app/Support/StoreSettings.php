<?php

namespace App\Support;

use App\Enums\PaymentMethod as PaymentMethodEnum;
use App\Models\Courier;
use App\Models\PaymentMethod;

/**
 * Pembacaan pengaturan toko yang dipakai di alur transaksi.
 *
 * Ekspedisi & metode bayar dikelola admin lewat tabel (couriers,
 * payment_methods); fallback ke konfigurasi bawaan HANYA bila tabel belum
 * di-seed (tidak ada baris sama sekali) — bila tabel ada tapi tidak ada
 * yang aktif, berarti admin sengaja menonaktifkan semua.
 */
final class StoreSettings
{
    /**
     * Ekspedisi aktif — [kode => nama].
     *
     * @return array<string, string>
     */
    public static function enabledCouriers(): array
    {
        $couriers = Courier::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'code');

        if ($couriers->isEmpty() && ! Courier::query()->withTrashed()->exists()) {
            return config('shipping.couriers');
        }

        return $couriers->all();
    }

    /**
     * Kode ekspedisi aktif (untuk validasi Rule::in).
     *
     * @return list<string>
     */
    public static function enabledCourierCodes(): array
    {
        return array_keys(self::enabledCouriers());
    }

    /**
     * Metode pembayaran aktif — [kode => nama].
     *
     * @return array<string, string>
     */
    public static function enabledPaymentMethods(): array
    {
        $methods = PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'code');

        if ($methods->isEmpty() && ! PaymentMethod::query()->withTrashed()->exists()) {
            return PaymentMethodEnum::options();
        }

        return $methods->all();
    }

    /**
     * Semua metode pembayaran (termasuk nonaktif) — untuk label order lama.
     *
     * @return array<string, string>
     */
    public static function allPaymentMethods(): array
    {
        return PaymentMethod::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'code')
            ->all() ?: PaymentMethodEnum::options();
    }

    /**
     * Nilai metode pembayaran aktif (untuk validasi Rule::in).
     *
     * @return list<string>
     */
    public static function enabledPaymentMethodValues(): array
    {
        return array_keys(self::enabledPaymentMethods());
    }
}
