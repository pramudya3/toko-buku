<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->boolean('is_custom_price')->default(false)->after('price_final');
            $table->unsignedInteger('custom_price')->nullable()->after('is_custom_price');
            $table->string('price_note', 255)->nullable()->after('custom_price');
            $table->uuid('promo_id_snapshot')->nullable()->after('price_note');
            $table->foreign('promo_id_snapshot')->references('id')->on('promotions')->nullOnDelete();
            $table->index('is_custom_price');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            try {
                Schema::table('order_items', function (Blueprint $table): void {
                    $table->dropIndex('order_items_is_custom_price_index');
                });
            } catch (Throwable) {
                try {
                    DB::statement('DROP INDEX IF EXISTS order_items_is_custom_price_index');
                } catch (Throwable) {
                }
            }

            Schema::table('order_items', function (Blueprint $table): void {
                try {
                    $table->dropForeign(['promo_id_snapshot']);
                } catch (Throwable) {
                }

                $table->dropColumn(['is_custom_price', 'custom_price', 'price_note', 'promo_id_snapshot']);
            });

            return;
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropForeign(['promo_id_snapshot']);
            $table->dropIndex(['is_custom_price']);
            $table->dropColumn(['is_custom_price', 'custom_price', 'price_note', 'promo_id_snapshot']);
        });
    }
};
