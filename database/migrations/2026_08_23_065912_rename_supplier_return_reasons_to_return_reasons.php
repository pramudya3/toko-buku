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
        // Rename tabel lama ke generik + tambah kolom type untuk multi-jenis retur
        if (Schema::hasTable('supplier_return_reasons') && ! Schema::hasTable('return_reasons')) {
            Schema::rename('supplier_return_reasons', 'return_reasons');
        }

        if (Schema::hasTable('return_reasons') && ! Schema::hasColumn('return_reasons', 'type')) {
            Schema::table('return_reasons', function (Blueprint $table) {
                $table->string('type', 30)->default('supplier')->after('category');
                $table->index('type');
            });

            // Set existing data jadi supplier
            DB::table('return_reasons')->update(['type' => 'supplier']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('return_reasons') && Schema::hasColumn('return_reasons', 'type')) {
            Schema::table('return_reasons', function (Blueprint $table) {
                $table->dropIndex(['type']);
                $table->dropColumn('type');
            });
        }

        if (Schema::hasTable('return_reasons') && ! Schema::hasTable('supplier_return_reasons')) {
            Schema::rename('return_reasons', 'supplier_return_reasons');
        }
    }
};
