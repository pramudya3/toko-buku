<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('supplier_purchase_items', function (Blueprint $table) {
            $table->integer('stock_before')->nullable()->after('subtotal');
            $table->integer('hpp_old')->nullable()->after('stock_before');
            $table->integer('hpp_new')->nullable()->after('hpp_old');
            $table->integer('landed_cost')->nullable()->after('hpp_new');
        });

        // Backfill existing rows: snapshot dari edition saat ini agar Show tidak kosong
        if (Schema::hasTable('supplier_purchase_items') && Schema::hasTable('book_editions')) {
            $items = DB::table('supplier_purchase_items')->get(['id', 'book_edition_id']);
            foreach ($items as $item) {
                $hpp = null;
                if ($item->book_edition_id) {
                    $hpp = DB::table('book_editions')->where('id', $item->book_edition_id)->value('harga_beli');
                }
                DB::table('supplier_purchase_items')->where('id', $item->id)->update([
                    'stock_before' => 0,
                    'hpp_old' => $hpp,
                    'hpp_new' => $hpp,
                    'landed_cost' => $hpp,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_purchase_items', function (Blueprint $table) {
            $table->dropColumn(['stock_before', 'hpp_old', 'hpp_new', 'landed_cost']);
        });
    }
};
