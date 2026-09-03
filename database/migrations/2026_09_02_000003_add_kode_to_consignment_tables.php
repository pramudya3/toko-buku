<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignment_deliveries', function (Blueprint $table) {
            $table->string('kode')->nullable()->unique()->after('id');
        });
        Schema::table('consignment_sales', function (Blueprint $table) {
            $table->string('kode')->nullable()->unique()->after('id');
        });
        Schema::table('consignment_returns', function (Blueprint $table) {
            $table->string('kode')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        // Urutan penting utk sqlite (UuidMigrationRollbackTest): drop index
        // unik dulu, baru kolomnya.
        Schema::table('consignment_deliveries', function (Blueprint $table) {
            $table->dropUnique(['kode']);
        });
        Schema::table('consignment_sales', function (Blueprint $table) {
            $table->dropUnique(['kode']);
        });
        Schema::table('consignment_returns', function (Blueprint $table) {
            $table->dropUnique(['kode']);
        });
        Schema::table('consignment_deliveries', function (Blueprint $table) {
            $table->dropColumn('kode');
        });
        Schema::table('consignment_sales', function (Blueprint $table) {
            $table->dropColumn('kode');
        });
        Schema::table('consignment_returns', function (Blueprint $table) {
            $table->dropColumn('kode');
        });
    }
};
