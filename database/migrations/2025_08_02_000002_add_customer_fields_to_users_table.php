<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status_pelanggan')->default('reguler')->after('is_admin');
            $table->string('whatsapp_number')->nullable()->after('status_pelanggan');
            $table->integer('tier_discount')->unsigned()->default(0)->after('whatsapp_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status_pelanggan', 'whatsapp_number', 'tier_discount']);
        });
    }
};
