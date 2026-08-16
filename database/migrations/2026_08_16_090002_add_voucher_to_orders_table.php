<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Voucher terpakai di order: relasi + snapshot kode (kalau voucher
     * dihapus/diubah) + besaran diskon (total = subtotal + ongkir − diskon).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUuid('voucher_id')->nullable()->after('sumber_pembelian')->constrained('vouchers')->nullOnDelete();
            $table->string('voucher_code_snapshot')->nullable()->after('voucher_id');
            $table->integer('voucher_discount_amount')->unsigned()->default(0)->after('voucher_code_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voucher_id');
            $table->dropColumn(['voucher_code_snapshot', 'voucher_discount_amount']);
        });
    }
};
