<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'client_uuid')) {
                $table->uuid('client_uuid')->nullable()->unique()->after('id');
                $table->index('client_uuid');
            }
            if (! Schema::hasColumn('orders', 'pos_meta')) {
                $table->jsonb('pos_meta')->nullable()->after('client_uuid');
            }
        });
        // Index for sync pull
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['sumber_pembelian', 'created_at'], 'idx_orders_sumber_created');
        });
    }

    public function down(): void
    {
        // Urutan penting utk sqlite: drop index dulu, baru kolomnya (kolom
        // dengan index tidak bisa didrop langsung di sqlite).
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_client_uuid_unique');
            $table->dropIndex('orders_client_uuid_index');
            $table->dropIndex('idx_orders_sumber_created');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['client_uuid', 'pos_meta']);
        });
    }
};
