<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penulis boleh kosong — sebagian buku hasil import CSV tidak memuat
 * penulis (mis. baris Poster di pricelist).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('penulis', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('penulis', 255)->nullable(false)->change();
        });
    }
};
