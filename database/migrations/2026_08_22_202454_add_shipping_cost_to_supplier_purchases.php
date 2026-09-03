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
        Schema::table('supplier_purchases', function (Blueprint $table) {
            $table->integer('shipping_cost')->unsigned()->default(0)->after('warehouse_kodes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_purchases', function (Blueprint $table) {
            $table->dropColumn('shipping_cost');
        });
    }
};
