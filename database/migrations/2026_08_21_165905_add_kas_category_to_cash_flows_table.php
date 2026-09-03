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
        Schema::table('cash_flows', function (Blueprint $table) {
            $table->uuid('kas_category_id')->nullable()->after('flow_type');
            $table->uuid('kas_sub_category_id')->nullable()->after('kas_category_id');

            // Foreign keys only on non-sqlite (sqlite in-memory tests don't support dropping them easily)
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->foreign('kas_category_id')->references('id')->on('cash_flow_categories')->nullOnDelete();
                $table->foreign('kas_sub_category_id')->references('id')->on('cash_flow_sub_categories')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_flows', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                try {
                    $table->dropForeign(['kas_category_id']);
                } catch (Throwable $e) {
                }
                try {
                    $table->dropForeign(['kas_sub_category_id']);
                } catch (Throwable $e) {
                }
            }

            $table->dropColumn(['kas_category_id', 'kas_sub_category_id']);
        });
    }
};
