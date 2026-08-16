<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Target diskon voucher: item (harga produk) atau ongkir (ongkos kirim).
     * Discount dihitung dari base yang sesuai — selain itu validasi (min
     * belanja, kuota) tetap memakai subtotal item.
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('discount_scope')->default('item')->after('voucher_type');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('discount_scope');
        });
    }
};
