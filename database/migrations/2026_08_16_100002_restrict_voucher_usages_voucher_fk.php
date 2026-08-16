<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Voucher yang sudah dipakai di pesanan tidak boleh dihapus (aturan
     * bisnis ditegakkan di app) — FK level DB di-restrict agar riwayat
     * pemakaian tidak bisa lenyap diam-diam lewat hard delete.
     */
    public function up(): void
    {
        Schema::table('voucher_usages', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->foreign('voucher_id')->references('id')->on('vouchers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('voucher_usages', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->foreign('voucher_id')->references('id')->on('vouchers')->cascadeOnDelete();
        });
    }
};
