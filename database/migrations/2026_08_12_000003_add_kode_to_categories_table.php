<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom kode (abreviasi) untuk kategori — dipakai generate SKU buku
 * otomatis: `{kode}{6 digit}` (mis. Parenting → PRN000008), mengikuti
 * pola kode barang dari file pricelist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('kode', 10)->nullable()->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('kode');
        });
    }
};
