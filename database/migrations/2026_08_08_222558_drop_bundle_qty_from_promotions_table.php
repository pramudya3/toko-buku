<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bundle_qty dihapus — bundle promo kini selalu 1 set:
 * diskon berlaku saat semua buku paket ada di keranjang (qty per buku bebas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('bundle_qty');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->unsignedInteger('bundle_qty')->nullable()->after('promo_value');
        });
    }
};
