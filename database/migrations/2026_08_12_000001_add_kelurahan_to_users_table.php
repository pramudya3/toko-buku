<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom kelurahan + kode wilayah desa untuk users.
 *
 * Form pelanggan (AddressFields) sudah mengirim `kelurahan` & `village_code`
 * tapi kolomnya belum ada — nilai selama ini hilang diam-diam. Kolom ini juga
 * dipakai importer CSV pelanggan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('kelurahan', 100)->nullable()->after('kecamatan');
            $table->string('village_code', 20)->nullable()->after('kelurahan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['kelurahan', 'village_code']);
        });
    }
};
