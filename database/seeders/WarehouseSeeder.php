<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Gudang bawaan: Malang, Sidoarjo, dan Defect (khusus barang cacat).
     */
    public function run(): void
    {
        Warehouse::updateOrCreate(
            ['kode' => 'malang'],
            ['nama' => 'Malang', 'is_defect' => false, 'is_active' => true],
        );

        Warehouse::updateOrCreate(
            ['kode' => 'sidoarjo'],
            ['nama' => 'Sidoarjo', 'is_defect' => false, 'is_active' => true],
        );

        Warehouse::updateOrCreate(
            ['kode' => 'defect'],
            ['nama' => 'Defect', 'is_defect' => true, 'is_active' => true],
        );

        Warehouse::updateOrCreate(
            ['kode' => 'toko'],
            ['nama' => 'Toko Fisik', 'alamat' => 'Toko Offline', 'is_defect' => false, 'is_active' => true],
        );
    }
}
