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
        Schema::table('orders', function (Blueprint $table): void {
            // Dashboard: where status=Selesai whereDate created_at, where metode_bayar + status
            $table->index(['status', 'created_at'], 'orders_status_created_at_index');
            $table->index(['metode_bayar', 'status'], 'orders_metode_bayar_status_index');
            $table->index(['status', 'updated_at'], 'orders_status_updated_at_index');
        });

        Schema::table('cash_flows', function (Blueprint $table): void {
            $table->index(['kas_category_id'], 'cash_flows_kas_category_id_index');
            $table->index(['kas_sub_category_id'], 'cash_flows_kas_sub_category_id_index');
            // Untuk filter bulan + flow_type
            $table->index(['flow_type', 'entry_date'], 'cash_flows_flow_type_entry_date_index');
        });

        Schema::table('cash_flow_months', function (Blueprint $table): void {
            $table->index(['is_closed'], 'cash_flow_months_is_closed_index');
        });
    }

    public function down(): void
    {
        Schema::table('cash_flow_months', function (Blueprint $table): void {
            $table->dropIndex('cash_flow_months_is_closed_index');
        });

        Schema::table('cash_flows', function (Blueprint $table): void {
            $table->dropIndex('cash_flows_kas_category_id_index');
            $table->dropIndex('cash_flows_kas_sub_category_id_index');
            $table->dropIndex('cash_flows_flow_type_entry_date_index');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_status_created_at_index');
            $table->dropIndex('orders_metode_bayar_status_index');
            $table->dropIndex('orders_status_updated_at_index');
        });
    }
};
