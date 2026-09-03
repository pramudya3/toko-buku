<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consignment_delivery_items', function (Blueprint $table) {
            $table->unsignedInteger('harga_asli')->default(0)->after('qty');
            $table->unsignedInteger('harga_titip')->default(0)->after('harga_asli');
        });
    }

    public function down(): void
    {
        Schema::table('consignment_delivery_items', function (Blueprint $table) {
            $table->dropColumn(['harga_asli', 'harga_titip']);
        });
    }
};
