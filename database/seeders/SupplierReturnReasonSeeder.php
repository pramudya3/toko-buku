<?php

namespace Database\Seeders;

use App\Models\SupplierReturnReason;
use Illuminate\Database\Seeder;

class SupplierReturnReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reasons = [
            ['code' => 'cacat-halaman-rusak', 'name' => 'Halaman Rusak / Sobek', 'category' => 'cacat', 'sort_order' => 1],
            ['code' => 'cacat-jilid-lepas', 'name' => 'Jilid Lepas', 'category' => 'cacat', 'sort_order' => 2],
            ['code' => 'cacat-cetak-buram', 'name' => 'Cetak Buram / Tinta Luntur', 'category' => 'cacat', 'sort_order' => 3],
            ['code' => 'cacat-kotor', 'name' => 'Buku Kotor / Bernoda', 'category' => 'cacat', 'sort_order' => 4],
            ['code' => 'salah-judul', 'name' => 'Salah Kirim Judul', 'category' => 'salah_kirim', 'sort_order' => 1],
            ['code' => 'salah-jumlah', 'name' => 'Salah Jumlah', 'category' => 'salah_kirim', 'sort_order' => 2],
            ['code' => 'salah-edisi', 'name' => 'Salah Edisi / Cetakan', 'category' => 'salah_kirim', 'sort_order' => 3],
            ['code' => 'umum-lainnya', 'name' => 'Lainnya', 'category' => 'umum', 'sort_order' => 99],
        ];

        foreach ($reasons as $reason) {
            SupplierReturnReason::firstOrCreate(
                ['code' => $reason['code']],
                $reason
            );
        }
    }
}
