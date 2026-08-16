<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot target diskon voucher di order — agar tampilan invoice/laporan
     * tetap jujur walau voucher diubah/dihapus setelah checkout.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('voucher_scope_snapshot')->default('item')->after('voucher_code_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('voucher_scope_snapshot');
        });
    }
};
