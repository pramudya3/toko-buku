<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('shipping_cost')->unsigned()->default(0)->after('total');
            $table->boolean('is_dropship')->default(false)->after('shipping_cost');
            $table->string('warehouse_origin')->nullable()->after('is_dropship');
            $table->string('status')->default('menunggu_konfirmasi')->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->integer('price_original')->unsigned()->default(0)->after('harga_snapshot');
            $table->integer('promo_discount_amount')->unsigned()->default(0)->after('price_original');
            $table->integer('tier_discount_amount')->unsigned()->default(0)->after('promo_discount_amount');
            $table->integer('price_final')->unsigned()->default(0)->after('tier_discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_cost', 'is_dropship', 'warehouse_origin']);
            $table->string('status')->default('baru')->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['price_original', 'promo_discount_amount', 'tier_discount_amount', 'price_final']);
        });
    }
};
