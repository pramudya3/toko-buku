<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('alamat', 500)->nullable()->after('tier_discount');
            $table->string('provinsi', 100)->nullable()->after('alamat');
            $table->string('kabupaten_kota', 100)->nullable()->after('provinsi');
            $table->string('kecamatan', 100)->nullable()->after('kabupaten_kota');
            $table->string('kode_pos', 10)->nullable()->after('kecamatan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['alamat', 'provinsi', 'kabupaten_kota', 'kecamatan', 'kode_pos']);
        });
    }
};
