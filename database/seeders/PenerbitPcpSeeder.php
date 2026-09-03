<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Restore penjualan bulan Agustus 2026 dari penjualan-agustus.csv
 * (full 01–31 Agustus, source terbaru) ke skema aplikasi.
 *
 * Satu perintah:
 *   php artisan db:seed --class=PenerbitPcpSeeder
 *
 * Menghasilkan: 7 kategori, 80+ buku, 100+ customer, dan ~120 faktur
 * (01–31 Agustus full) senilai ~Rp 37.4jt.
 * - Order biasa/dropship -> orders + order_items + dropshippers
 * - Konsinyasi (Jns Bayar mengandung "konsinyasi") -> consignment_deliveries + consignment_sales + receivables
 *   - Konsinyasi dropship (Pak Nuris -> Namira) -> customer_id = Dropshiped, notes "via Pak Nuris"
 * - Bundling tetap 1 SKU Bdl, tidak di-split (historis append-only).
 */
class PenerbitPcpSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PenerbitPcpMasterSeeder::class,
            PenerbitPcpSalesSeeder::class,
        ]);
    }
}
