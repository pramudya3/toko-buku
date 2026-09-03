<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('supplier_return_items', function (Blueprint $table) {
            $table->integer('stock_before')->nullable()->after('subtotal');
            $table->integer('hpp_at_return')->nullable()->after('stock_before');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_return_items', function (Blueprint $table) {
            $table->dropColumn(['stock_before', 'hpp_at_return']);
        });
    }
};
