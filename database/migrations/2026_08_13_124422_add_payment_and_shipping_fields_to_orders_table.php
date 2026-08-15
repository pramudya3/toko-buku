<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bukti transfer (upload user) + data pengiriman Biteship di order.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('bukti_transfer_path')->nullable()->after('payment_status');
            $table->timestamp('bukti_transfer_at')->nullable()->after('bukti_transfer_path');

            $table->string('biteship_order_id')->nullable()->after('ongkir_estimasi');
            $table->string('awb')->nullable()->after('biteship_order_id');
            $table->string('biteship_label_url')->nullable()->after('awb');
            $table->string('biteship_status')->nullable()->after('biteship_label_url');
            $table->string('courier_service_code')->nullable()->after('ekspedisi');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'bukti_transfer_path',
                'bukti_transfer_at',
                'biteship_order_id',
                'awb',
                'biteship_label_url',
                'biteship_status',
                'courier_service_code',
            ]);
        });
    }
};
