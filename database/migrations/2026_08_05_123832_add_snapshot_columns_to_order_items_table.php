<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan order_items dengan versi migration terbaru
 * (judul_snapshot, harga_snapshot) pada database yang dibuat dari versi lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'judul_snapshot')) {
                $table->string('judul_snapshot')->nullable()->after('book_id');
            }

            if (! Schema::hasColumn('order_items', 'harga_snapshot')) {
                $table->integer('harga_snapshot')->unsigned()->nullable()->after('judul_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['judul_snapshot', 'harga_snapshot']);
        });
    }
};
