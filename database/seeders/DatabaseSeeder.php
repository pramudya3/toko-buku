<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed dasar untuk semua environment (termasuk production):
     * gudang, ekspedisi, metode pembayaran, dan akun superadmin tester.
     *
     * Data demo (kategori, buku, order, dsb.) TIDAK otomatis — hanya untuk
     * pengembangan:
     *   php artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        $this->call([
            WarehouseSeeder::class,
            CourierSeeder::class,
            PaymentMethodSeeder::class,
            SalesChannelSeeder::class,
            KasCategorySeeder::class,
            SuperAdminSeeder::class,
            // Akun customer demo — untuk mencoba alur storefront sebagai pembeli.
            CustomerSeeder::class,
            // Data referensi wilayah (provinsi/kabupaten/kecamatan/kelurahan + kode pos) —
            // wajib untuk AddressFields di checkout & pengaturan.
            WilayahSeeder::class,
            // Artikel demo storefront editorial — kategori + artikel terbit
            // (termasuk 1 unggulan untuk hero beranda).
            ArticleSeeder::class,
            // 20 buku demo storefront — kategori + edisi + stok gudang.
            BookSeeder::class,
        ]);
    }
}
