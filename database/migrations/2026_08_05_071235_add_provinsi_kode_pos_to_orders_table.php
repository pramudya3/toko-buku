<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom alamat lengkap ke orders (provinsi, kabupaten_kota,
 * kecamatan, kode_pos) — dengan guard agar aman di DB lama/baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'provinsi')) {
                $table->string('provinsi')->nullable()->after('alamat');
            }

            if (! Schema::hasColumn('orders', 'kabupaten_kota')) {
                $table->string('kabupaten_kota')->nullable()->after('provinsi');
            }

            if (! Schema::hasColumn('orders', 'kecamatan')) {
                $table->string('kecamatan')->nullable()->after('kabupaten_kota');
            }

            if (! Schema::hasColumn('orders', 'kode_pos')) {
                $table->string('kode_pos')->nullable()->after('kecamatan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['provinsi', 'kabupaten_kota', 'kecamatan', 'kode_pos']);
        });
    }
};
