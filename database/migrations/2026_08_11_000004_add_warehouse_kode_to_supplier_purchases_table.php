<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_purchases', function (Blueprint $table) {
            // Gudang tujuan barang masuk — dipakai retur untuk menentukan
            // gudang asal secara otomatis.
            $table->string('warehouse_kode', 50)->nullable()->index()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_purchases', function (Blueprint $table) {
            $table->dropIndex(['warehouse_kode']);
            $table->dropColumn('warehouse_kode');
        });
    }
};
