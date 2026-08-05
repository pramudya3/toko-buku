<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan inventory_movements dengan versi migration terbaru
 * (kolom reference & notes) pada database yang dibuat dari versi lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_movements', 'reference')) {
                $table->string('reference')->nullable()->after('qty');
            }

            if (! Schema::hasColumn('inventory_movements', 'notes')) {
                $table->text('notes')->nullable()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropColumn(['reference', 'notes']);
        });
    }
};
