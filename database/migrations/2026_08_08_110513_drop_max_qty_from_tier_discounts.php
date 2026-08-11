<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tier_discounts', function (Blueprint $table) {
            $table->dropUnique(['tier', 'min_qty', 'max_qty']);
            $table->dropColumn('max_qty');
            $table->unique(['tier', 'min_qty']);
        });
    }

    public function down(): void
    {
        Schema::table('tier_discounts', function (Blueprint $table) {
            $table->dropUnique(['tier', 'min_qty']);
            $table->unsignedInteger('max_qty')->nullable()->after('min_qty');
            $table->unique(['tier', 'min_qty', 'max_qty']);
        });
    }
};
