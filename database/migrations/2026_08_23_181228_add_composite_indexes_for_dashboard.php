<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement('CREATE INDEX IF NOT EXISTS orders_status_created_at_index ON orders (status, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS orders_metode_bayar_created_at_index ON orders (metode_bayar, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS supplier_purchases_supplier_purchase_date_index ON supplier_purchases (supplier_id, purchase_date)');
        DB::statement('CREATE INDEX IF NOT EXISTS supplier_returns_supplier_return_date_index ON supplier_returns (supplier_id, return_date)');
        DB::statement('CREATE INDEX IF NOT EXISTS books_aktif_stok_index ON books (aktif, stok)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement('DROP INDEX IF EXISTS orders_status_created_at_index');
        DB::statement('DROP INDEX IF EXISTS orders_metode_bayar_created_at_index');
        DB::statement('DROP INDEX IF EXISTS supplier_purchases_supplier_purchase_date_index');
        DB::statement('DROP INDEX IF EXISTS supplier_returns_supplier_return_date_index');
        DB::statement('DROP INDEX IF EXISTS books_aktif_stok_index');
    }
};
