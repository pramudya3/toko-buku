<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link tracking kurir dari Biteship (courier_link) — untuk tombol
     * "Lacak" di menu Pesanan Saya tanpa biaya API tracking.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('biteship_courier_link')->nullable()->after('biteship_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('biteship_courier_link');
        });
    }
};
