<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Tambah kolom `penterjemah` (buku terjemahan).
 * - Hapus kolom preorder (`is_preorder`, `po_label`) — pencatatan stok
 *   & preorder tidak lagi dikelola dari form buku (stok via barang masuk).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('penterjemah')->nullable()->after('penulis');
            $table->dropColumn(['is_preorder', 'po_label']);
            // Harga tidak lagi diinput manual — disinkron dari cetakan aktif.
            $table->integer('harga')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('penterjemah');
            $table->boolean('is_preorder')->default(false)->after('aktif');
            $table->string('po_label')->nullable()->after('is_preorder');
            $table->integer('harga')->nullable(false)->change();
        });
    }
};
