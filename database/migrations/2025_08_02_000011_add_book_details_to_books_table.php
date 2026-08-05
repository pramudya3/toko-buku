<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('dimensi')->nullable()->after('isbn');
            $table->string('kemasan')->nullable()->after('dimensi');
            $table->integer('berat_gr')->unsigned()->nullable()->after('kemasan');
            $table->integer('jumlah_halaman')->unsigned()->nullable()->after('berat_gr');
            $table->string('jenis_kertas')->nullable()->after('jumlah_halaman');
            $table->string('cetakan')->nullable()->after('jenis_kertas');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['dimensi', 'kemasan', 'berat_gr', 'jumlah_halaman', 'jenis_kertas', 'cetakan']);
        });
    }
};
