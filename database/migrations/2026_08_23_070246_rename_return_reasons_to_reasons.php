<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('return_reasons') && ! Schema::hasTable('reasons')) {
            Schema::rename('return_reasons', 'reasons');
        }
        // Fallback: jika masih ada supplier_return_reasons (belum di-rename sebelumnya)
        if (Schema::hasTable('supplier_return_reasons') && ! Schema::hasTable('reasons')) {
            Schema::rename('supplier_return_reasons', 'reasons');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('reasons') && ! Schema::hasTable('return_reasons')) {
            Schema::rename('reasons', 'return_reasons');
        }
    }
};
