<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan nama kolom alamat users dengan versi migration terbaru
 * (kabupaten → kabupaten_kota) pada database yang dibuat dari versi lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'kabupaten') && ! Schema::hasColumn('users', 'kabupaten_kota')) {
                $table->renameColumn('kabupaten', 'kabupaten_kota');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'kabupaten_kota') && ! Schema::hasColumn('users', 'kabupaten')) {
                $table->renameColumn('kabupaten_kota', 'kabupaten');
            }
        });
    }
};
