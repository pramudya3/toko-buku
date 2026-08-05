<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('kota');
            $table->string('kabupaten_kota')->nullable()->after('alamat');
            $table->string('kecamatan')->nullable()->after('kabupaten_kota');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['kabupaten_kota', 'kecamatan']);
            $table->string('kota')->nullable()->after('alamat');
        });
    }
};
